<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\Traits\HasRouteUlid;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'avatar_url', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRouteUlid, HasApiTokens;

    /**
     * Diekspos ke API (mis. GET /profile) supaya frontend tahu role user
     * yang sebenarnya, bukan label statis.
     */
    protected $appends = ['role_name'];

    public function getRoleNameAttribute(): ?string
    {
        return $this->roles->pluck('name')->first();
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url ? Storage::disk('public')->url($this->avatar_url) : null;
    }

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

    /**
     * Data karyawan (PIC) yang tertaut ke akun ini, kalau ada.
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function hasPermissionTo(string $permission): bool
    {
        if ($this->hasRole('Superadmin')) {
            return true;
        }

        // Check if user has direct permission
        if ($this->permissions()->where('name', $permission)->exists()) {
            return true;
        }

        // Check if any of the user's roles have the permission
        return $this->roles()->whereHas('permissions', function ($q) use ($permission) {
            $q->where('name', $permission);
        })->exists();
    }

    public const PANEL_PERMISSIONS = [
        'admin' => 'panel.admin',
        'inventory' => 'panel.inventory',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        // KODE LAMA (dijadikan komentar):
        // $permission = self::PANEL_PERMISSIONS[$panel->getId()] ?? null;
        // if ($permission === null) {
        //     return false;
        // }
        // return $this->hasPermissionTo($permission);

        // KODE BARU:
        // Agar Superadmin tidak melihat pemisahan panel (switcher), kita blokir aksesnya ke panel 'inventory'.
        // Karena menu inventaris sudah disatukan ke panel 'admin', Superadmin cukup pakai panel admin saja.
        if ($this->hasRole('Superadmin') && $panel->getId() === 'inventory') {
            return false;
        }

        $permission = self::PANEL_PERMISSIONS[$panel->getId()] ?? null;

        if ($permission === null) {
            return false;
        }

        return $this->hasPermissionTo($permission);
    }
}
