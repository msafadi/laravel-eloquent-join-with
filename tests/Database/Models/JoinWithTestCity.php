<?php

namespace Safadi\Tests\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Safadi\EloquentJoinWith\Database\Concerns\JoinWith;

class JoinWithTestCity extends Model
{

    use JoinWith;

    protected $table = 'cities';

    protected $fillable = ['id', 'country_id'];

    public $timestamps = false;

    public function country()
    {
        return $this
            ->belongsTo(JoinWithTestCountry::class, 'country_id', 'id')
            ->withDefault();
    }
}
