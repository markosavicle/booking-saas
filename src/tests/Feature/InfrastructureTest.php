<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    public function test_mysql_database_connection_is_successful(): void
    {
        $pdo = DB::connection()->getPdo();
        $this->assertInstanceOf(\PDO::class, $pdo);
	// Assert that the active driver matches what the environment configured
        $expectedDriver = config('database.default');
        $this->assertEquals($expectedDriver, DB::connection()->getDriverName());
    }

    public function test_redis_connection_is_successful(): void
    {
        // Ping Redis to ensure the predis client and docker network are working
        $response = Redis::connection()->ping();
        
        // Predis returns a Status object containing "PONG", or true depending on the config
        $payload = is_object($response) ? $response->getPayload() : $response;
        
        $this->assertTrue($payload === 'PONG' || $payload === true, 'Redis did not return PONG');
    }
}
