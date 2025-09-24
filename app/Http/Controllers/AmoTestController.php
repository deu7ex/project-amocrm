<?php

namespace App\Http\Controllers;

use App\Services\AmoCrmService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AmoTestController extends Controller
{
    /**
     * @throws RequestException
     */
    public function hook(Request $request): JsonResponse
    {
        Log::info('AmoCRM callback received', [
            'body' => json_encode($request->all(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]);

        return response()->json([
            'status' => 'ok'
        ]);
    }

    /**
     * @throws RequestException
     */
    public function createLead(Request $request, AmoCrmService $amo): JsonResponse
    {
        $leadId = $amo->createLead([
            'name'  => $request->name,
            'price' => $request->amount,
        ], [
            'email' => $request->email,
            'phone' => $request->phone,
            'company' => $request->company,
            'contact' => $request->contact
        ]);

        return response()->json([
            'status' => 'ok',
            'lead_id' => $leadId
        ]);
    }

    public function callback(Request $request)
    {
        try {
            // Логируем всё входящее
            Log::info('AmoCRM callback received', [
                'body' => json_decode($request->getContent(), true),
            ]);

            return response()->json([
                'status' => 'ok',
                'received' => $request->all(),
            ]);
        } catch (\Throwable $e) {
            // Логируем ошибку, но не роняем 500
            Log::error('AmoCRM callback error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal processing failed, but request logged',
            ]);
        }
    }
}
