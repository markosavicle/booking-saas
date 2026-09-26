<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Claim marker for the reminder command: a non-null value means the reminder
     * has already been dispatched and must never be sent again.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->timestamp('reminder_sent_at')->nullable()->after('status');

            $table->index(['reminder_sent_at', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex(['reminder_sent_at', 'start_time']);
            $table->dropColumn('reminder_sent_at');
        });
    }
};
