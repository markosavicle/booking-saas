<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            // IANA identifier (e.g. Europe/Belgrade). Appointments are stored in UTC;
            // business hours are wall-clock times in this zone.
            $table->string('timezone', 64)->default('UTC')->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};
