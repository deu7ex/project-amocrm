<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Sheets;

class GoogleService
{
    private static Sheets $sheets;

    public static function getClient(): Sheets
    {
        return self::$sheets ??= self::getSheetClient();
    }

    private static function getSheetClient(): Sheets {
        $client = new GoogleClient();
        $client->useApplicationDefaultCredentials();
        $client->setScopes([Sheets::SPREADSHEETS]);

        return new Sheets($client);
    }

}
