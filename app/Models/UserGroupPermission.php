<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['permission'])]
class UserGroupPermission extends Model
{
    public $timestamps = false;
}
