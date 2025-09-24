<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AmqpService;
use PhpAmqpLib\Exception\AMQPIOException;

class RabbitSheetsConsumerWorker extends Command
{
    protected $signature = 'rabbitmq:sheets-consume {queue=sheets_queue}';
    protected $description = 'Consume messages from a RabbitMQ queue';

    /**
     * @throws AMQPIOException
     */
    public function handle(): void
    {
        $queue = AmqpService::QUEUE_SHEETS;

        $this->info("📥 Consuming from queue: {$queue}");
        AmqpService::consume($queue, $queue . '_retry');
    }
}

