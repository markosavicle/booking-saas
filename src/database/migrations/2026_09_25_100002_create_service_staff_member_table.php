<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which staff members are qualified to perform which services.
     */
    public function up(): void
    {
        Schema::create('service_staff_member', function (Blueprint $table): void {
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained()->cascadeOnDelete();

            $table->primary(['service_id', 'staff_member_id']);
            $table->index('staff_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_staff_member');
    }
};
