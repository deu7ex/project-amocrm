<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class SheetsCacheService
{

    public function findLead(string $spreadsheetId, string|int $leadId): ?array
    {
        $validKey = "sheet_cache_valid:$spreadsheetId";

        if (!Cache::get($validKey, true)) {
            $this->invalidateAll($spreadsheetId);
        }

        return \App\Services\SheetsService::findByLeadIdCached($spreadsheetId, $leadId);
    }

    /**
     * Инвалидировать кэш по таблице
     */
    public function invalidate(string $spreadsheetId): void
    {
        Cache::put("sheet_cache_valid:$spreadsheetId", false, now()->addMinutes(10));
    }

    /**
     * Полная очистка кэша
     */
    public function invalidateAll(string $spreadsheetId): void
    {
        Cache::tags("sheet:$spreadsheetId")->flush();
        Cache::put("sheet_cache_valid:$spreadsheetId", true, now()->addDay());
    }
}
