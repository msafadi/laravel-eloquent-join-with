<?php

namespace Safadi\Tests;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Benchmark;
use Safadi\EloquentJoinWith\Database\Concerns\JoinWith;
use PHPUnit\Framework\TestCase;
use Safadi\Tests\Database\Models\JoinWithTestCity;
use Safadi\Tests\Database\Models\JoinWithTestCountry;
use Safadi\Tests\Database\Models\JoinWithTestProfile;
use Safadi\Tests\Database\Models\JoinWithTestUser;

class BenchmarkJoinWithTest extends TestCase
{
    public function testBenchmarkJoinWith()
    {
        $this->seedData();

        print_r(Benchmark::measure([
            'with'     => fn() => JoinWithTestUser::with('profile.city')->get(),
            'joinWith' => fn() => JoinWithTestUser::joinWith('profile.city')->get(),
        ]));

        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        $db = new Manager;

        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $db->bootEloquent();
        $db->setAsGlobal();

        $this->createSchema();
    }

    /**
     * Tear down the database schema.
     */
    protected function tearDown(): void
    {
        $this->schema()->drop('profiles');
        $this->schema()->drop('countries');
        $this->schema()->drop('cities');
        $this->schema()->drop('users');
    }

    /**
     * Get a database connection instance.
     *
     * @return \Illuminate\Database\ConnectionInterface
     */
    protected function connection()
    {
        return Model::getConnectionResolver()->connection();
    }

    /**
     * Get a schema builder instance.
     *
     * @return \Illuminate\Database\Schema\Builder
     */
    protected function schema()
    {
        return $this->connection()->getSchemaBuilder();
    }

    /**
     * Setup the database schema.
     *
     * @return void
     */
    public function createSchema()
    {
        $this->schema()->create('users', function ($table) {
            $table->increments('id');
        });

        $this->schema()->create('countries', function ($table) {
            $table->increments('id');
        });

        $this->schema()->create('cities', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('country_id');
        });

        $this->schema()->create('profiles', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('city_id');
            $table->enum('type', ['seller', 'buyer'])->default('seller');
        });
    }

    /**
     * Helpers...
     */
    protected function seedData()
    {

        $type = ['seller', 'buyer'];
        for ($i = 1; $i <= 10; $i++) {
            JoinWithTestCountry::create(['id' => $i]);
        }
        for ($i = 1; $i <= 10; $i++) {
            JoinWithTestCity::create(['id' => $i, 'country_id' => rand(1, 10)]);
        }

        for ($i = 1; $i <= 1000; $i++) {
            JoinWithTestUser::create(['id' => $i]);
            JoinWithTestProfile::create([
                'id' => $i,
                'user_id' => $i,
                'city_id' => rand(1, 10),
                'type' => $type[rand(0, 1)],
            ]);
        }
    }
}
