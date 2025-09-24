<?php

namespace App\Jobs;

use App\Services\SheetsService;
use Google\Service\Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class RefreshLeadMappingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $spreadsheetId,
        public int|string $leadId
    ) {}

    /**
     * @throws Exception
     */
    public function handle(SheetsService $sheets): void
    {
        $resp = $sheets::findSheetsMeta();

        if (!$resp) {
            return;
        }

        $found = $sheets::findByLeadId($resp['valueRanges'], $resp['indexToSheetId'], $this->leadId);

        if ($found) {
            Cache::put(
                "lead_mapping:$this->spreadsheetId:$this->leadId",
                $found,
                now()->addDay()
            );
        }
    }
}
