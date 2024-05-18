<?php

namespace Msafadi\LaravelJoinWith\Database\Eloquent;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Msafadi\LaravelJoinWith\Database\Concerns\JoinWith;

class Model extends Eloquent
{
    use JoinWith;
}