<?php

namespace Msafadi\Tests;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Msafadi\LaravelJoinWith\Database\Concerns\JoinWith;
use PHPUnit\Framework\TestCase;

class DatabaseEloquentJoinWithTest extends TestCase
{
    public function testJoinWithHasOne()
    {
        $this->seedData();

        $userWithProfile = JoinWithTestUser::joinWith('profile')
            ->select('users.id')
            ->find(1);

        $this->assertInstanceOf(JoinWithTestProfile::class, $userWithProfile->profile);
    }

    public function testJoinWithHasOneProfile()
    {
        $this->seedData();

        $userWithProfile = JoinWithTestUser::joinWith('profile', function ($query) {
            $query->where('type', '=', 'buyer');
        })->find(1);

        $this->assertEquals(2, $userWithProfile->profile->id);
    }

    public function testJoinWithHasOneRelationAbsence()
    {
        $this->seedData();

        $userWithProfile = JoinWithTestUser::joinWith('profile')->find(2);

        $this->assertNull($userWithProfile->profile);
    }

    public function testJoinWithHasOneRelationAbsenceWithDefault()
    {
        $this->seedData();

        $userWithProfile = JoinWithTestUser::joinWith('profileWithDefault')->find(2);

        $this->assertNotNull($userWithProfile->profileWithDefault);
        $this->assertEquals('seller', $userWithProfile->profileWithDefault->type);
    }

    public function testJoinWithBelongsToRelation()
    {
        $this->seedData();

        $profileWithUser = JoinWithTestProfile::joinWith('user')->find(1);

        $this->assertEquals(1, $profileWithUser->user->id);
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
        JoinWithTestUser::create(['id' => 1]);
        JoinWithTestUser::create(['id' => 2]);

        JoinWithTestCountry::create(['id' => 1]);
        JoinWithTestCountry::create(['id' => 2]);

        JoinWithTestProfile::create([
            'id' => 1,
            'user_id' => 1,
            'country_id' => 1,
            'type' => 'seller',
        ]);

        JoinWithTestProfile::create([
            'id' => 2,
            'user_id' => 1,
            'country_id' => 2,
            'type' => 'buyer',
        ]);

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
            ->belongsTo(JoinWithTestCountry::class, 'country_id', 'id');
    }
}

class JoinWithTestCountry extends Model
{
    use JoinWith;

    protected $table = 'countries';

    protected $fillable = ['id'];

    public $timestamps = false;

}