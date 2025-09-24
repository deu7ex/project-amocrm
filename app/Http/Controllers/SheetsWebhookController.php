<?php

namespace App\Http\Controllers;

use App\Services\SheetsCacheService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SheetsWebhookController extends Controller
{

    public function handle(Request $request, SheetsCacheService $cache): JsonResponse
    {
        if ($request->input('type') === 'STRUCTURE_CHANGED') {
            $spreadsheetId = $request->input('spreadsheetId');
            $cache->invalidate($spreadsheetId);

            return response()->json(['status' => 'cache invalidated']);
        }

        return response()->json(['status' => 'ignored']);
    }
}
