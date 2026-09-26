<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GalleryImage;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryImage>
 */
class GalleryImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'path' => GalleryImage::DIRECTORY.'/'.fake()->uuid().'.jpg',
            'caption' => fake()->randomElement(['Skin fade', 'Beard sculpt', 'The shop floor', null]),
            'sort_order' => 0,
        ];
    }
}
