<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable so pre-existing appointments survive the deploy. Staff should be
     * deactivated rather than deleted; deleting one un-assigns its appointments.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->foreignId('staff_member_id')->nullable()->after('service_id')
                ->constrained()->nullOnDelete();

            $table->index(['staff_member_id', 'start_time', 'end_time']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex(['staff_member_id', 'start_time', 'end_time']);
            $table->dropConstrainedForeignId('staff_member_id');
        });
    }
};
