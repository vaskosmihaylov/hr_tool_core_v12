<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
     * Determine if the user can access the Filament admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Allow access if user has admin role or can access admin panel
        return $this->hasAnyRole(['admin', 'manager']) || $this->can('access_admin_panel');
    }

    /**
     * Activity log configuration.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Check if user has permission for specific URL path
     */
    public function hasPermissionUrl($url)
    {
        $originalUrl = $url;

        $resource = $this->findUrlResource($url, Resource::RESOURCE_TYPE_RELATIVE);

        if (!$resource && strrpos($url, '/')) {

            do {
                $url = substr($url, 0, strrpos($url, '/'));

                $resource = $this->findUrlResource($url, Resource::RESOURCE_TYPE_RELATIVE);

                if ($resource) {
                    break;
                }

            } while (strrpos($url, '/'));
        }

        if ($resource) {

            $permission = $resource->permission()->first();

            if ($permission && $this->can($permission->name)) {
                return true;
            } else if ($this->checkPermissionAbsolutePath($originalUrl)) {
                return true;
            } else {
                return false;
            }

        } else {

            $resource = $this->findUrlResource($originalUrl, Resource::RESOURCE_TYPE_ABSOLUTE);

            if ($resource && $this->checkPermissionForResource($resource)) {
                return true;
            } else if ($resource) {
                return false;
            } else {
                return true;
            }
        }
    }

    public function checkPermissionAbsolutePath($url)
    {
        $resource = $this->findUrlResource($url, Resource::RESOURCE_TYPE_ABSOLUTE);

        if ($resource && $this->checkPermissionForResource($resource)) {
            return true;
        }

        return false;
    }

    private function findUrlResource($url, $type)
    {
        //url is identical to resource
        $resource = $this->getUrlResource($url, $type);

        //url is /some/action/5 and resource is /some/action/{?}
        if (!$resource && strrpos($url, '/')) {

            $wildcardUrl = substr($url, 0, strrpos($url, '/')) . '/{?}';

            $resource = $this->getUrlResource($wildcardUrl, $type);
        }

        //url is /some/action and resource is /some/action/{?}
        if (!$resource) {
            $wildcardUrl = $url . '/{?}';
            $resource = $this->getUrlResource($wildcardUrl, $type);
        }

        return $resource;

    }

    private function getUrlResource($url, $type)
    {
        return Resource::where('type', '=', $type)
            ->whereIn('value', [
                $url,
                $url . '/',
                '/'. $url,
                '/' . $url . '/'])
            ->first();
    }

    private function checkPermissionForResource($resource) {

        $permission = $resource->permission()->first();

        if ($permission && !$this->can($permission->name)) {
            return false;
        }

        return true;
    }
}
