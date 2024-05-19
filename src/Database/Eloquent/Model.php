<?php

namespace Safadi\EloquentJoinWith\Database\Eloquent;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Safadi\EloquentJoinWith\Database\Concerns\JoinWith;

class Model extends Eloquent
{
    use JoinWith;
}