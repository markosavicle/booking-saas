<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class GalleryImage extends Model
{
    use BelongsToTenant, HasFactory;

    /** Directory on the `public` disk that holds uploaded gallery photos. */
    public const string DIRECTORY = 'tenants/gallery';

    protected $fillable = [
        'tenant_id',
        'path',
        'caption',
        'sort_order',
    ];

    protected static function booted(): void
    {
        // Replaced or removed photos would otherwise pile up on the public disk forever.
        static::updated(function (GalleryImage $image): void {
            if ($image->wasChanged('path')) {
                Storage::disk('public')->delete($image->getRawOriginal('path'));
            }
        });

        static::deleted(fn (GalleryImage $image) => Storage::disk('public')->delete($image->path));
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
