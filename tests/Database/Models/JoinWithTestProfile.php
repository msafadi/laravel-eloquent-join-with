<?php

namespace Safadi\Tests\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Safadi\EloquentJoinWith\Database\Concerns\JoinWith;

class JoinWithTestProfile extends Model
{
    use JoinWith;

    protected $table = 'profiles';

    protected $fillable = ['id', 'user_id', 'type', 'city_id'];

    public $timestamps = false;

    public function user()
    {
        return $this
            ->belongsTo(JoinWithTestUser::class, 'user_id', 'id');
    }

    public function city()
    {
        return $this
            ->belongsTo(JoinWithTestCity::class, 'city_id', 'id')
            ->withDefault();
    }
}
