<?php

namespace App\Listeners;

use App\Events\LeadSyncFailed;
use Illuminate\Support\Facades\DB;

class StoreFailedLead
{
    public function handle(LeadSyncFailed $event): void
    {
        DB::table('failed_leads')->insert([
            'lead_id'    => $event->task['leadId'] ?? null,
            'payload'    => json_encode($event->task),
            'error'      => $event->exception->getMessage(),
            'created_at' => now(),
        ]);
    }
}
