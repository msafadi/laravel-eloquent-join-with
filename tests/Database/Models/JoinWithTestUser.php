<?php

namespace Safadi\Tests\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Safadi\EloquentJoinWith\Database\Concerns\JoinWith;

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
