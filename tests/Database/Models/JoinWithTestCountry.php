<?php

namespace Safadi\Tests\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Safadi\EloquentJoinWith\Database\Concerns\JoinWith;

class JoinWithTestCountry extends Model
{
    use JoinWith;

    protected $table = 'countries';

    protected $fillable = ['id'];

    public $timestamps = false;
}
