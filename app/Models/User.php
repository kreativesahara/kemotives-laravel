<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['first_name', 'last_name', 'email', 'password', 'roles', 'refresh_token', 'reset_token', 'reset_token_expiry'])]
#[Hidden(['password', 'remember_token', 'refresh_token', 'reset_token', 'reset_token_expiry'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function toArray()
    {
        $array = parent::toArray();
        $array['firstname'] = $array['first_name'] ?? null;
        $array['lastname'] = $array['last_name'] ?? null;
        
        unset($array['first_name'], $array['last_name']);
        
        return $array;
    }
}
