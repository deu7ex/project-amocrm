<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadSyncFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $task,
        public \Throwable $exception
    ) {}
}
