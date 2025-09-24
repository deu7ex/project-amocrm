<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('failed_leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_id')->nullable();
            $table->json('payload');
            $table->text('error');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_leads');
    }
};
