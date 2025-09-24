<?php

namespace App\Services;

use Faker\Factory;
use Faker\Generator;

class FakerService
{

    private static ?Generator $faker = null;

    public static function get(): Generator
    {
        return self::$faker ??= Factory::create();
    }

}
