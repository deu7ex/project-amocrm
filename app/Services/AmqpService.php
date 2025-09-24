<?php

namespace App\Services;

use App\Jobs\SyncSheetsJob;
use App\Jobs\SyncVkJob;
use App\Services\RabbitmqService;
use Exception;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use App\Jobs\SyncLeadJob;
use Illuminate\Support\Facades\Log;

class AmqpService
{
    const QUEUE_LEADS = 'amocrm_task_v2';
    const QUEUE_SHEETS = 'sheets_task_v2';
    const QUEUE_VK = 'vk_task_v2';
    const QUEUE_LIST = [
        self::QUEUE_LEADS,
        self::QUEUE_SHEETS,
        self::QUEUE_VK
    ];

    /**
     * @throws AMQPIOException
     * @throws Exception
     */
    public static function publish(string $index, string $index_retry, array $data): void
    {
        try {
            if (!in_array($index, self::QUEUE_LIST)) {
                Log::warning("Попытка добавить в неизвестную очередь: $index");
                return;
            }

            $channel = RabbitmqService::getChannel();

            // Retry очередь
            $channel->queue_declare($index_retry, false, true, false, false, false, [
                'x-message-ttl' => ['I', 30000],
                'x-dead-letter-exchange' => ['S', ''],
                'x-dead-letter-routing-key' => ['S', $index],
            ]);

            // Основная очередь
            $channel->queue_declare($index, false, true, false, false, false, [
                'x-dead-letter-exchange' => ['S', ''],
                'x-dead-letter-routing-key' => ['S', $index_retry],
            ]);

            $msg = new AMQPMessage(json_encode($data), [
                'delivery_mode' => 2, // persist
            ]);

            $channel->basic_publish($msg, '', $index);

            Log::info('AmoCRM publish message successfully sent', [
                'body' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ]);
        } catch (AMQPIOException $exc) {
            RabbitmqService::close();
            throw new AMQPIOException($exc->getMessage());
        }
    }

    /**
     * @throws AMQPIOException
     * @throws Exception
     */
    public static function consume(string $queue, string $index_retry): void
    {
        try {
            if (!in_array($queue, self::QUEUE_LIST)) {
                Log::warning("Попытка подписаться на неизвестную очередь: $queue");
                return;
            }

            $channel = RabbitmqService::getChannel();

            $channel->queue_declare($queue, false, true, false, false, false, [
                'x-dead-letter-exchange' => ['S', ''],
                'x-dead-letter-routing-key' => ['S', $index_retry],
            ]);

            echo "[*] Waiting for messages on `$queue`...\n";

            Log::info("[*] Waiting for messages on `$queue`...\n", [
                'body' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ]);

            $callback = function (AMQPMessage $msg) use ($queue) {
                $json = json_decode($msg->getBody(), true);

                echo '[>] [' . now() . '] Received: ' . $msg->getBody() . PHP_EOL;

                Log::info('AmoCRM consumer message successfully sent', [
                    'body' => json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                ]);

                match ($queue) {
                    self::QUEUE_LEADS  => SyncLeadJob::dispatch($json),
                    self::QUEUE_SHEETS => SyncSheetsJob::dispatch($json),
                    self::QUEUE_VK => SyncVkJob::dispatch($json),
                    default => Log::warning("[$queue] Unknown queue message", ['body' => $msg->getBody()]),
                };

                echo '[✔] [' . now() . '] Done!' . PHP_EOL . PHP_EOL;

                $msg->ack();
            };

            $channel->basic_qos(0, 1, false);
            $channel->basic_consume($queue, '', false, false, false, false, $callback);

            while ($channel->is_consuming()) {
                try {
                    $channel->wait();
                } catch (AMQPTimeoutException $e) {
                    echo '[!] Timeout: no message received' . PHP_EOL;
                } catch (AMQPConnectionClosedException $e) {
                    echo '[✘] Connection closed: ' . $e->getMessage() . PHP_EOL;
                    break;
                }
            }
        } catch (AMQPIOException $e) {
            RabbitmqService::close();
            throw new AMQPIOException("RabbitMQ Consumer Error: " . $e->getMessage());
        }
    }
}
