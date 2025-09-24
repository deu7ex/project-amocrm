<?php

namespace App\Services;

use Google\Service\Exception;
use Google\Service\Sheets;
use Google_Service_Sheets_BatchUpdateSpreadsheetRequest;
use Google_Service_Sheets_Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SheetsService
{
    private static Sheets $sheets;

    /**
     * @throws Exception
     */
    public static function findSheetsMeta(): ?array
    {
        self::$sheets = GoogleService::getClient();
        $spreadsheet = self::$sheets->spreadsheets->get(env('AMOCRM_SPREADSHEET_ID'));
        $sheetsMeta = $spreadsheet->getSheets();

        if (!$sheetsMeta) {
            return null;
        }

        $ranges = [];
        $indexToSheetId = [];

        foreach ($sheetsMeta as $idx => $meta) {
            $title = $meta->getProperties()->getTitle();
            $sheetId = $meta->getProperties()->getSheetId();
            $rowCount = $meta->getProperties()->getGridProperties()->getRowCount() ?? 1000;

            $ranges[] = sprintf('%s!%s1:%s%d', $title,
                env('AMOCRM_AMOCRM_ID_COLUMN'), env('AMOCRM_AMOCRM_ID_COLUMN'), $rowCount);

            $indexToSheetId[$idx] = $sheetId;
        }

        $resp = self::$sheets->spreadsheets_values->batchGet(env('AMOCRM_SPREADSHEET_ID'), ['ranges' => $ranges]);

        return [
            'valueRanges' => $resp->getValueRanges(),
            'indexToSheetId' => $indexToSheetId
        ];
    }

    public static function findByLeadId(array $valueRanges, array $indexToSheetId, string|int $leadId): ?array
    {
        try {
            if (!$valueRanges || !$indexToSheetId) {
                return null;
            }

            foreach ($valueRanges as $i => $vr) {
                $values = $vr->getValues() ?? [];
                foreach ($values as $rowIndex0 => $rowVals) {
                    $cell = $rowVals[0] ?? '';
                    if ((string)$cell === $leadId) {
                        return [
                            'sheetId' => $indexToSheetId[$i],
                            'row' => $rowIndex0 + 1,
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            logger()->error('Sheets error', [
                'status' => $e->getCode(),
                'body' => $e->getMessage(),
                'errors' => $e->getErrors()
            ]);
        }

        // Не нашли
        return null;
    }

    /**
     * Обновить одну ячейку.
     */
    public static function updateCell(
        string|int $sheetId,
        string|int|float $value,
        int $rowIndex,
        int $columnIndex
    ): void {
        try {
            $requests = self::pushSingleRow($sheetId, $value, $rowIndex, [], $columnIndex);
            self::batchUpdateRequest($requests);
        } catch (Exception $e) {
            logger()->error('Sheets error', [
                'status' => $e->getCode(),
                'body' => $e->getMessage(),
                'errors' => $e->getErrors()
            ]);
        }
    }

    /**
     * @throws Exception
     */
    public static function findByLeadIdCached(string $spreadsheetId, array $idList): ?array
    {
        $mapping = [];
        $idNotList = [];

        foreach ($idList as $leadId) {
            $cacheKey = "lead_mapping:$spreadsheetId:$leadId";
            $cached = Cache::get($cacheKey);

            if ($cached) {
                $mapping[$leadId] = $cached;
                continue;
            }

            $idNotList[] = $leadId;
        }

        $mapping = self::checkIdNotList($spreadsheetId, $idNotList, $mapping);

        return $mapping ?: null;
    }

    /**
     * @throws Exception
     */
    private static function checkIdNotList(string $spreadsheetId, array $idNotList, array $mapping): ?array
    {
        if (!$idNotList) {
            return $mapping;
        }

        $resp = self::findSheetsMeta();

        if (
            !$resp
            || empty($resp['valueRanges'])
            || empty($resp['indexToSheetId'])
        ) {
            return $mapping;
        }

        foreach ($idNotList as $leadId) {
            $found = self::findByLeadId($resp['valueRanges'], $resp['indexToSheetId'], $leadId);

            if ($found) {
                $cacheKey = "lead_mapping:$spreadsheetId:$leadId";
                Cache::put($cacheKey, $found, now()->addDay());
                $mapping[$leadId] = $found;
            }
        }

        return $mapping;
    }

    private static function pushSingleRow(
        $sheetId,
        string $value,
        int $rowIndex,
        array $requests,
        int $columnIndex
    ): array {
        $requests[] = new Google_Service_Sheets_Request([
            'updateCells' => [
                'rows' => [
                    [
                        'values' => [
                            'userEnteredValue' => ['stringValue' => $value],
                            'userEnteredFormat' => [
                                'verticalAlignment' => 'MIDDLE',
                                'horizontalAlignment' => 'LEFT',
                                'wrapStrategy' => 'CLIP'
                            ]
                        ]
                    ]
                ],
                'fields' => '*',
                'start' => [
                    'sheetId' => $sheetId,
                    'rowIndex' => $rowIndex,
                    'columnIndex' => $columnIndex
                ]
            ]
        ]);

        return $requests;
    }

    /**
     * @throws Exception
     */
    private static function getMapping(array $deal, array $contact): ?array
    {
        $idList = [];

        if ($deal && !empty($deal['id'])) {
            $idList = [$deal['id']];
        } elseif ($contact && !empty($contact['linked_leads_id'])) {
            $idList = array_keys($contact['linked_leads_id']);
        }

        Log::info('AmoCRM sheet id list', [
            'body' => json_encode($idList, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]);

        Log::info('AmoCRM sheet deel list', [
            'body' => json_encode($deal, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]);

        Log::info('AmoCRM sheet contact list', [
            'body' => json_encode($contact, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]);

        if (!$idList) {
            return null;
        }

        $mapping = self::findByLeadIdCached(env('AMOCRM_SPREADSHEET_ID'), $idList);

        Log::info('AmoCRM sheet mapping', [
            'body' => json_encode($mapping, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]);

        return $mapping ?: null;
    }

    /**
     * @throws Exception
     */
    public static function updateCells(array $payload): void
    {
        $contact = $payload['contacts']['update'][0] ?? [];
        $deal = $payload['leads']['update'][0] ?? [];

        $mapping = self::getMapping($deal, $contact);

        if (!$mapping) {
            return;
        }

        $requests = [];

        foreach ($mapping as $pos) {
            $rowIndex = $pos['row'] - 1;
            $sheetId  = $pos['sheetId'];

            if (!empty($deal['price'])) {
                $requests = self::pushSingleRow($sheetId, (string) $deal['price'], $rowIndex,
                    $requests, env('AMOCRM_SHEET_PRICE_INDEX'));
            }

            if (!empty($deal['name'])) {
                $requests = self::pushSingleRow($sheetId, (string) $deal['name'], $rowIndex,
                    $requests, env('AMOCRM_SHEET_NAME_INDEX'));
            }

            if (!empty($contact['name'])) {
                $requests = self::pushSingleRow($sheetId, (string) $contact['name'], $rowIndex,
                    $requests, env('AMOCRM_SHEET_CONTACT_INDEX'));
            }

            if (!empty($contact['custom_fields'])) {
                $email = $phone = '';

                foreach ($contact['custom_fields'] as $field) {
                    if ($field['code'] === 'PHONE' && !empty($field['values'][0]['value'])) {
                        $phone = $field['values'][0]['value'];
                    }

                    if ($field['code'] === 'EMAIL' && !empty($field['values'][0]['value'])) {
                        $email = $field['values'][0]['value'];
                    }
                }

                $requests = self::pushSingleRow($sheetId, (string) $email, $rowIndex, $requests, env('AMOCRM_SHEET_EMAIL_INDEX'));
                $requests = self::pushSingleRow($sheetId, (string) $phone, $rowIndex, $requests, env('AMOCRM_SHEET_PHONE_INDEX'));
            }
        }

        if ($requests) {
            self::batchUpdateRequest($requests);
        }
    }

    /**
     * @throws Exception
     */
    private static function batchUpdateRequest($requests): void
    {
        $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest(['requests' => $requests]);

        $client = GoogleService::getClient();
        $client->spreadsheets->batchUpdate(env('AMOCRM_SPREADSHEET_ID'), $batchUpdateRequest);
    }


}
