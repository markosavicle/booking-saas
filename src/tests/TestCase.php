<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Last line of defence: RefreshDatabase runs migrate:fresh, so refuse to
     * touch anything but in-memory SQLite. This hooks setUpTraits() because the
     * trait's own beforeRefreshingDatabase() would shadow an override here.
     */
    protected function setUpTraits()
    {
        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $connection = config('database.default');
            $database = config("database.connections.{$connection}.database");

            if ($connection !== 'sqlite' || $database !== ':memory:') {
                throw new RuntimeException(
                    "Refusing to refresh database [{$connection}:{$database}]; tests must run on in-memory SQLite.",
                );
            }
        }

        return parent::setUpTraits();
    }
}
