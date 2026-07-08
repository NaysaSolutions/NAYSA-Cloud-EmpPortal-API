<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'paymast';
    protected $primaryKey = 'EMPNO';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public function getAuthPassword()
    {
        return $this->getAttribute('ASKAPP_PW');
    }
}