<?php

namespace Safadi\Tests;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Benchmark;
use Safadi\EloquentJoinWith\Database\Concerns\JoinWith;
use PHPUnit\Framework\TestCase;

class BenchmarkJoinWithTest extends TestCase
{
    public function testBenchmarkJoinWith()
    {
        $this->seedData();

        print_r(Benchmark::measure([
            'with'     => fn() => JoinWithTestUser::with('profile.country')->get(),
            'joinWith' =>fn() => JoinWithTestUser::joinWith('profile.country')->get(),
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

        $this->schema()->create('profiles', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('country_id');
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
        
        for ($i = 1; $i <= 1000; $i++) {
            JoinWithTestUser::create(['id' => $i]);
            JoinWithTestProfile::create([
                'id' => $i,
                'user_id' => $i,
                'country_id' => rand(1, 10),
                'type' => $type[rand(0, 1)],
            ]);
        }

    }
}

class JoinWithTestUser extends Model
{
    use JoinWith;

    protected $table = 'users';

    protected $fillable = ['id'];

    public $timestamps = false;

    public function profile()
    {
        return $this
            ->hasOne(JoinWithTestProfile::class, 'user_id', 'id');
    }

    public function profileWithDefault()
    {
        return $this
            ->hasOne(JoinWithTestProfile::class, 'user_id', 'id')
            ->withDefault([
                'type' => 'seller',
            ]);
    }
}

class JoinWithTestProfile extends Model
{
    use JoinWith;

    protected $table = 'profiles';

    protected $fillable = ['id', 'user_id', 'country_id', 'type'];

    public $timestamps = false;

    public function user()
    {
        return $this
            ->belongsTo(JoinWithTestUser::class, 'user_id', 'id');
    }

    public function country()
    {
        return $this
            ->belongsTo(JoinWithTestCountry::class, 'country_id', 'id')
            ->withDefault();
    }
}

class JoinWithTestCountry extends Model
{
    use JoinWith;

    protected $table = 'countries';

    protected $fillable = ['id'];

    public $timestamps = false;

}