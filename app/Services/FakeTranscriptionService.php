<?php

namespace App\Services;

use App\Models\Segment;

class FakeTranscriptionService
{
    public static function run(int $taskId): array
    {
        $start = faker()->randomFloat(2, 0, 30);
        $end = $start + faker()->randomFloat(2, 1, 5);

        $data = [
            'task_id' => $taskId,
            'speaker' => faker()->randomElement(['S1', 'S2', 'S3']),
            'start' => $start,
            'end' => $end,
            'text' => faker()->sentence(8)
        ];

        Segment::create($data);

        return $data;
    }
}
