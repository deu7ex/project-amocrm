<?php

namespace App\Services;

use Google\Service\Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class VkontakteService
{

    /**
     * @throws RequestException
     */
    public static function fromVK(array $payload, AmoCrmService $amo): void
    {
        $leadId = $amo->getDeal($payload['vkId']);

        if (!$leadId) {
            $leadId = $amo->newDeal($payload['vkId']);
        }

        if (!$leadId) {
            return;
        }

        $author = self::getVkUserData($payload['vkId']);
        $amo->addAmoNote($leadId, $payload['note'], $author);
    }

    private static function getVkUserData(int $vkUserId): string
    {
        $cacheKey = "vk_user:$vkUserId";
        $cached = Cache::get($cacheKey);

        if ($cached) {
            return $cached;
        }

        $name = "VK user $vkUserId";
        $token = env('VK_API_TOKEN');

        try {
            $response = Http::get('https://api.vk.com/method/users.get', [
                'user_ids' => $vkUserId,
                'access_token' => $token,
                'v' => (string) env('VK_API_VERSION')
            ])->json();

            $user = $response['response'][0] ?? null;

            if ($user) {
                $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            }
        } catch (Exception $e) {
            logger()->error('Sheets error', [
                'status' => $e->getCode(),
                'body' => $e->getMessage(),
                'errors' => $e->getErrors()
            ]);
        }

        Cache::put($cacheKey, $name, now()->addDay());

        return $name;
    }


}
