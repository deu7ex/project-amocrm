<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AmqpService;
use PhpAmqpLib\Exception\AMQPIOException;

class RabbitVkontakteConsumerWorker extends Command
{
    protected $signature = 'rabbitmq:vk-consume {queue=vk_queue}';
    protected $description = 'Consume messages from a RabbitMQ queue';

    /**
     * @throws AMQPIOException
     */
    public function handle(): void
    {
        $queue = AmqpService::QUEUE_VK;

        $this->info("📥 Consuming from queue: {$queue}");
        AmqpService::consume($queue, $queue . '_retry');
    }
}

