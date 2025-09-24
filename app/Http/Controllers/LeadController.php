<?php

namespace App\Http\Controllers;

use App\Services\AmqpService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Exception\AMQPIOException;

class LeadController extends Controller
{
    /**
     * @throws AMQPIOException
     */
    public function store(Request $request, AmqpService $sheets): JsonResponse
    {
        Log::info('AmoCRM sheet received', [
            'body' => json_encode($request->all(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]);

        $retry = $sheets::QUEUE_LEADS . '_retry';
        $sheets->publish($sheets::QUEUE_LEADS, $retry, $request->all());

        return response()->json(['status' => 0]);
    }

    /**
     * @throws AMQPIOException
     */
    public function google(Request $request, AmqpService $sheets): JsonResponse
    {
        $payload = $request->all();

        Log::info('AmoCRM sheet upd', [
            'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]);

        $retry = $sheets::QUEUE_SHEETS . '_retry';
        $sheets->publish($sheets::QUEUE_SHEETS, $retry, $payload);

        return response()->json(['status' => 0]);
    }

    /**
     * @throws AMQPIOException
     */
    public function vk(Request $request, AmqpService $amq)
    {
        $payload = $request->all();

        Log::info('AmoCRM vk', [
            'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]);

        if (
            !empty($payload['type'])
            && $payload['type'] === 'confirmation'
            && (int) $payload['group_id'] === (int) env('VK_GROUP_ID')
        ) {
            return response(env('VK_CONFIRM_CODE'), 200)
                ->header('Content-Type', 'text/plain');
        }

        $data = [
            'vkId' => $payload['object']['message']['from_id'],
            'note' => $payload['object']['message']['text']
        ];

        $retry = $amq::QUEUE_VK . '_retry';
        $amq->publish($amq::QUEUE_VK, $retry, $data);

        return response('ok', 200)->header('Content-Type', 'text/plain');
    }

}
