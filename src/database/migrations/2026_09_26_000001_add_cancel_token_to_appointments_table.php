<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('cancel_token', 64)->nullable()->after('status');
        });

        // Existing bookings get a token too, so every appointment has a working link.
        DB::table('appointments')->whereNull('cancel_token')->orderBy('id')->lazyById()->each(
            fn (object $row) => DB::table('appointments')->where('id', $row->id)->update(['cancel_token' => Str::random(48)]),
        );

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('cancel_token', 64)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique(['cancel_token']);
            $table->dropColumn('cancel_token');
        });
    }
};
