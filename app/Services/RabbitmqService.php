<?php

namespace App\Services;

use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;

class RabbitmqService
{
    private static ?AMQPStreamConnection $connection = null;
    private static ?AMQPChannel $channel = null;

    /**
     * @throws Exception
     */
    public static function getChannel(): AMQPChannel
    {
        return self::$channel ??= self::connect();
    }

    /**
     * @throws Exception
     */
    private static function connect(): AMQPChannel
    {
        self::$connection = new AMQPStreamConnection(
            config('queue.connections.rabbitmq.host'),
            config('queue.connections.rabbitmq.port'),
            config('queue.connections.rabbitmq.user'),
            config('queue.connections.rabbitmq.password')
        );

        return self::$connection->channel();
    }

    /**
     * @throws Exception
     */
    public static function close(): void
    {
        if (self::$channel) {
            self::$channel->close();
            self::$connection->close();

            self::$channel = null;
            self::$connection = null;
        }
    }
}
