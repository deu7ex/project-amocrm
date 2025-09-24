<?php

namespace App\Jobs;

use App\Services\AmoCrmService;
use App\Events\LeadSyncFailed;
use App\Services\SheetsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;
use DateTime;


class SyncLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $task;
    public int $tries = 5;
    public array $backoff = [10, 30, 60, 120];

    public function __construct(array $task)
    {
        $this->task = $task;
    }

    public function retryUntil(): DateTime
    {
        return now()->addMinutes(10);
    }

    private function normalizePrice($value): int
    {
        if (is_null($value) || $value === '') {
            return 0;
        }

        $normalized = str_replace(',', '.', preg_replace('/\s+/', '', (string)$value));
        return is_numeric($normalized) ? (int) round($normalized) : 0;
    }

    /**
     * @throws Throwable
     * @throws ValidationException
     */
    public function handle(AmoCrmService $amo): void
    {
        $payload = $this->task['payload'];

        $payload['phone'] = (string)$this->task['payload']['phone'];
        $payload['email'] = (string)$this->task['payload']['email'];
        $payload['price'] = $this->normalizePrice($this->task['payload']['price']);

        $validated = Validator::make($payload, [
            'leadId' => ['nullable', 'integer'],
            'name' => ['required', 'string'],
            'phone' => ['required', 'string'],
            'email' => ['required', 'string'],
            'price' => ['required', 'integer'],
            'company' => ['required', 'string'],
            'contact' => ['required', 'string']
        ])->validate();

        try {
            if (empty($validated['leadId'])) {
                $leadId = $amo->createLead($validated);

                if ($leadId) {
                    SheetsService::updateCell(
                        $this->task['sheetId'],
                        $leadId,
                        $this->task['row'] - 1,
                        config('amocrm.column_amo_index')
                    );
                }
            } else {
                $amo->updateLead($validated['leadId'], $validated);
            }
        } catch (Throwable $e) {
            $code = $e->getCode();

            if ($code === 400) {
                Log::warning('SyncLeadJob: невалидные данные', [
                    'task' => $this->task,
                    'error' => $e->getMessage(),
                ]);
                event(new LeadSyncFailed($this->task, $e));
                $this->fail($e);
                return;
            }

            if (in_array($code, [429, 500])) {
                throw $e;
            }

            Log::error('SyncLeadJob: ошибка', [
                'task' => $this->task,
                'code' => $code,
                'error' => $e->getMessage(),
            ]);
            event(new LeadSyncFailed($this->task, $e));
            $this->fail($e);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('SyncLeadJob окончательно упала', [
            'task' => $this->task,
            'error' => $e->getMessage(),
        ]);
        event(new LeadSyncFailed($this->task, $e));
    }
}

