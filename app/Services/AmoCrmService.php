<?php

namespace App\Services;

use App\Models\AmoCrmToken;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmoCrmService
{
    private string $domain;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->domain = config('services.amocrm.domain');
        $this->clientId = config('services.amocrm.client_id');
        $this->clientSecret = config('services.amocrm.client_secret');
        $this->redirectUri = env('AMOCRM_REDIRECT_URI');
    }

    private function getAccessToken(): string
    {
        return Cache::remember('amocrm_access_token', 3600, function () {
            return $this->refreshToken();
        });
    }

    /**
     * @throws RequestException
     * @throws Exception
     */
    private function refreshToken(): string
    {
        $token = AmoCrmToken::where('integration_id', 'default')->first();

        if (!$token || !$token->refresh_token) {
            throw new Exception('Нет refresh_token, нужно авторизоваться заново.');
        }

        $response = Http::post("https://$this->domain/oauth2/access_token", [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $token->refresh_token,
            'redirect_uri' => $this->redirectUri,
        ])->throw()->json();

        $this->saveTokens($response);

        Cache::put('amocrm_access_token', $response['access_token'], $response['expires_in']);

        return $response['access_token'];
    }

    private function saveTokens(array $response): void
    {
        AmoCrmToken::updateOrCreate(
            ['integration_id' => 'default'],
            [
                'access_token' => $response['access_token'],
                'refresh_token' => $response['refresh_token'],
                'expires_at' => Carbon::now()->addSeconds($response['expires_in']),
            ]
        );
    }

    /**
     * @throws RequestException
     */
    public function request(string $method, string $uri, array $data = [])
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->baseUrl("https://$this->domain/api/v4/")
            ->acceptJson()
            ->{$method}(
                $uri,
                $data
            );

        if ($response->status() === 401) {
            $token = $this->refreshToken();
            $response = Http::withToken($token)
                ->baseUrl("https://$this->domain/api/v4/")
                ->acceptJson()
                ->{$method}(
                    $uri,
                    $data
                );
        }

        $response->throw();

        return $response->json();
    }

    /**
     * @throws RequestException
     */
    public function authByCode(string $code): void
    {
        $response = Http::post("https://$this->domain/oauth2/access_token", [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
        ])->throw()->json();

        $this->saveTokens($response);

        Cache::put('amocrm_access_token', $response['access_token'], $response['expires_in']);
    }

    private function pickExactByEmail(array $res, string $email): ?int
    {
        foreach (data_get($res, '_embedded.contacts', []) as $c) {
            foreach ((array)data_get($c, 'custom_fields_values', []) as $cf) {
                foreach ((array)data_get($cf, 'values', []) as $v) {
                    if (strcasecmp((string)($v['value'] ?? ''), $email) === 0) {
                        return (int)$c['id'];
                    }
                }
            }
        }
        return null;
    }

    private function pickExactByPhone(array $res, string $cleanPhone): ?int
    {
        foreach (data_get($res, '_embedded.contacts', []) as $c) {
            foreach ((array)data_get($c, 'custom_fields_values', []) as $cf) {
                foreach ((array)data_get($cf, 'values', []) as $v) {
                    $val = preg_replace('/\D+/', '', (string)($v['value'] ?? ''));
                    if ($val === $cleanPhone) {
                        return (int)$c['id'];
                    }
                }
            }
        }
        return null;
    }

    /**
     * @throws RequestException
     */
    private function getCompanyId($contactData)
    {
        if (empty($contactData['company'])) {
            return null;
        }

        $search = $this->request('get', 'companies', [
            'query' => $contactData['company'],
            'limit' => 1,
        ]);

        if (!empty($search['_embedded']['companies'][0])) {
            return $search['_embedded']['companies'][0]['id'];
        }

        $newCompany = $this->request('post', 'companies', [
            [
                'name' => $contactData['company'],
            ]
        ]);

        if (!empty($newCompany[0]['id'])) {
            return $newCompany[0]['id'];
        }

        return null;
    }

    /**
     * @throws RequestException
     */
    public function getDeal(string|int $vkId)
    {
        $cacheKey = "vk_deal:$vkId";
        $cached = Cache::get($cacheKey);

        if ($cached) {
            return $cached;
        }

        $leadId = null;

        try {
            $response = $this->request('get', 'leads', [
                "filter[custom_fields_values][" . env('VK_AMOCRM_FIELD_ID') . "]" => $vkId,
                'limit' => 1
            ]);

            $leadId = $response['_embedded']['leads'][0]['id'] ?? null;

            if ($leadId) {
                Cache::put($cacheKey, $leadId, now()->addDay());
            }
        } catch (RequestException $e) {
            if ($e->response->status() !== 400) {
                throw $e;
            }
        }

        return $leadId;
    }

    /**
     * @throws RequestException
     */
    public function newDeal(string|int $vkId)
    {
        $leadData = [
            [
                "name" => "Заявка из ВК ($vkId)",
                "custom_fields_values" => [
                    [
                        "field_id" => env('VK_AMOCRM_FIELD_ID'),
                        "values" => [
                            ["value" => (string) $vkId]
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->request('post', 'leads', $leadData);
        $leadId = $response['_embedded']['leads'][0]['id'] ?? null;

        if ($leadId) {
            $cacheKey = "vk_deal:$vkId";
            Cache::put($cacheKey, $leadId, now()->addDay());
        }

        return $leadId;
    }

    /**
     * @throws RequestException
     */
    public function addAmoNote(int $leadId, string $text, string $authorName = null): void
    {
        $noteText = $authorName
            ? "{$authorName} (VK): {$text}"
            : $text;

        $noteData = [
            [
                "note_type" => "common",
                "params" => [
                    "text" => $noteText
                ]
            ]
        ];

        $this->request('post', "leads/{$leadId}/notes", $noteData);
    }


    /**
     * @throws RequestException
     * @throws Exception
     */
    private function getContactId($contactData, $companyId)
    {
        $contactId = null;
        $existing = $this->request('get', 'contacts', [
            'limit' => 25
        ]);

        if (!empty($contactData['email'])) {
            $contactId = $this->pickExactByEmail($existing, $contactData['email']);
        }

        if (!$contactId && !empty($contactData['phone'])) {
            $contactId = $this->pickExactByPhone($existing, $contactData['phone']);
        }

        if ($contactId) {
            return $contactId;
        }

        $payload = [
            'name' => $contactData['contact'] ?? 'Новый контакт',
            'custom_fields_values' => array_filter([
                !empty($contactData['phone']) ? [
                    'field_code' => 'PHONE',
                    'values' => [['value' => $contactData['phone']]]
                ] : null,
                !empty($contactData['email']) ? [
                    'field_code' => 'EMAIL',
                    'values' => [['value' => $contactData['email']]]
                ] : null,
            ])
        ];

        if ($companyId) {
            $payload['companies'] = ['id' => $companyId];
        }

        $created = $this->request('post', 'contacts', [$payload]);
        $contactId = $created['_embedded']['contacts'][0]['id'] ?? null;

        if (!$contactId) {
            throw new Exception('Не удалось создать или найти контакт в AmoCRM');
        }

        return $contactId;
    }

    /**
     * Создание сделки
     * @throws RequestException
     * @throws Exception
     */
    public function createLead(array $leadData): ?int
    {
        $companyId = $this->getCompanyId($leadData);
        $contactId = $this->getContactId($leadData, $companyId);

        $leadPayload = [
            'name' => $leadData['name'] ?? 'Новая сделка',
            'price' => $leadData['price'] ?? 0,
            'pipeline_id' => (int)env('AMOCRM_PIPELINE_ID'),
            'status_id' => (int)env('AMOCRM_STATUS_ID'),
            '_embedded' => [
                'contacts' => [
                    ['id' => $contactId]
                ]
            ]
        ];

        if ($companyId) {
            $leadPayload['_embedded']['companies'][] = ['id' => $companyId];
        }

        $lead = $this->request('post', 'leads', [$leadPayload]);
        return $lead['_embedded']['leads'][0]['id'] ?? null;
    }

    /**
     * Обновление сделки
     * @throws RequestException
     */
    public function updateLead(int $leadId, array $data): bool
    {
        $payload = array_merge(['id' => $leadId], $data);
        $this->request('patch', 'leads', [$payload]);

        return true;
    }
}
