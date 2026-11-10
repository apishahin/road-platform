<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUlids, Notifiable;

    protected $fillable = ['phone', 'display_name', 'role', 'is_banned', 'phone_verified_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'is_banned'         => 'boolean',
            'phone_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // Generate a ULID for public_id only; keep the numeric id as primary key
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function isModerator(): bool
    {
        return in_array($this->role, ['moderator', 'admin'], true);
    }

    /** Moderator/admin, or a volunteer who has at least one active area. */
    public function canModerate(): bool
    {
        if ($this->isModerator()) {
            return true;
        }

        return $this->role === 'volunteer'
            && ! $this->is_banned
            && $this->activeModeratorAreas()->exists();
    }

    public function moderatorAreas(): HasMany
    {
        return $this->hasMany(ModeratorArea::class);
    }

    public function activeModeratorAreas(): HasMany
    {
        return $this->moderatorAreas()->whereNull('revoked_at');
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
}