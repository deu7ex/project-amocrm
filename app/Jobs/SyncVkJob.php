<?php

namespace App\Jobs;

use App\Events\LeadSyncFailed;
use App\Services\AmoCrmService;
use App\Services\VkontakteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;
use DateTime;


class SyncVkJob implements ShouldQueue
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

    /**
     * @throws Throwable
     * @throws ValidationException
     */
    public function handle(VkontakteService $vk, AmoCrmService $amo): void
    {
        try {
            $vk::fromVK($this->task, $amo);
        } catch (Throwable $e) {
            $code = $e->getCode();

            if ($code === 400) {
                Log::warning('SyncVkJob: невалидные данные', [
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

            Log::error('SyncVkJob: ошибка', [
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
        Log::error('SyncVkJob окончательно упала', [
            'task' => $this->task,
            'error' => $e->getMessage(),
        ]);
        event(new LeadSyncFailed($this->task, $e));
    }
}

