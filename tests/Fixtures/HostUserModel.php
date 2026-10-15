<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LaraSaaS\Concerns\HasWorkspaces;

/**
 * Stand-ins for HOST application models, used by the test suite to prove that
 * migrations and relations follow config('larasaas.user_model') / the
 * configured workspace model instead of any hardcoded table.
 */

class User extends \Illuminate\Foundation\Auth\User
{
    use HasWorkspaces;

    protected $guarded = [];
}

class Member extends \Illuminate\Foundation\Auth\User
{
    protected $table = 'members';

    protected $guarded = [];
}

class Team extends Model
{
    protected $table = 'teams';

    protected $guarded = [];
}
