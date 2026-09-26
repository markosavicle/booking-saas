<?php

declare(strict_types=1);

namespace Tests\Fakes;

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;

/**
 * Framework's withoutVite() stub, plus fonts(): as a real public method on Vite it
 * bypasses the stub's __call and would still require a built font manifest.
 */
final class NullVite extends Vite
{
    public function __invoke($entrypoints, $buildDirectory = null)
    {
        return new HtmlString('');
    }

    public function fonts($aliases = null)
    {
        return new HtmlString('');
    }

    public function __toString()
    {
        return '';
    }
}
