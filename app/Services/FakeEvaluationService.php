<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\Task;

class FakeEvaluationService
{
    public static function run(int $taskId): void
    {
        sleep(3);

        Evaluation::create([
            'task_id' => $taskId,
            'score' => faker()->randomFloat(2, 1, 10),
            'summary' => faker()->sentence(10),
            'raw' => ['tone' => 'neutral', 'keywords' => ['hello', 'problem']]
        ]);

        Task::where('id', $taskId)
            ->update(['status' => Task::COMPLETE_STATUS]);
    }
}
