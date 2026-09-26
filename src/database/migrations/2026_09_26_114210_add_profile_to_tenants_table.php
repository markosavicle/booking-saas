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
            // ISO 4217 code; service prices are stored as plain decimals in this currency.
            $table->char('currency', 3)->default('EUR')->after('timezone');
            $table->string('tagline', 160)->nullable()->after('currency');
            $table->text('about_text')->nullable()->after('tagline');
            // Free-form, one line per row of the postal address.
            $table->string('address')->nullable()->after('about_text');
            $table->string('phone', 32)->nullable()->after('address');
            $table->string('social_instagram')->nullable()->after('phone');
            $table->string('social_facebook')->nullable()->after('social_instagram');
            // Relative to the `public` disk; null falls back to the bundled stock photo.
            $table->string('hero_image_path')->nullable()->after('social_facebook');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn([
                'currency', 'tagline', 'about_text', 'address', 'phone',
                'social_instagram', 'social_facebook', 'hero_image_path',
            ]);
        });
    }
};
