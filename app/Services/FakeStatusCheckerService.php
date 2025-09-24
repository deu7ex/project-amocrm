<?php

namespace App\Services;

class FakeStatusCheckerService
{
    public static function check($task): int
    {
        // эмулируем прогресс
        $statuses = [2, 2, 2, 2, 3, 3, 4]; // вероятность того, что "выполнен"
        return faker()->randomElement($statuses);
    }
}
