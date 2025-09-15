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
     * Get the regions that belong to the user.
     * For managers - they manage specific regions
     */
    public function regions()
    {
        return $this->belongsToMany(
            'viki\Service\Models\Elequent\Region',
            'viki_user_region',
            'user_id',
            'region_id'
        );
    }

    /**
     * Get the workplaces that belong to the user.
     * For supervisors - they supervise specific workplaces
     */
    public function workPlaces()
    {
        return $this->belongsToMany(
            'viki\Service\Models\Elequent\WorkPlace',
            'viki_supervisor_work_place',
            'supervisor_id',
            'work_place_id'
        );
    }

    /**
     * Get active workplaces for the user.
     */
    public function activeWorkPlaces()
    {
        return $this->belongsToMany(
            'viki\Service\Models\Elequent\WorkPlace',
            'viki_supervisor_work_place',
            'supervisor_id',
            'work_place_id'
        )->where('viki_work_place.status', 0); // WORK_PLACE_ACTIVE = 0
    }

    /**
     * Assign the given region to the user.
     *
     * @param  string $region
     * @return mixed
     */
    public function assignRegion($region)
    {
        $regionModel = is_string($region) 
            ? \viki\Service\Models\Elequent\Region::whereName($region)->firstOrFail()
            : $region;
            
        return $this->regions()->save($regionModel);
    }

    /**
     * Assign the given work place to the user.
     *
     * @param  string $workPlace
     * @return mixed
     */
    public function assignWorkPlace($workPlace)
    {
        $workPlaceModel = is_string($workPlace)
            ? \viki\Service\Models\Elequent\WorkPlace::whereName($workPlace)->firstOrFail()
            : $workPlace;
            
        return $this->workPlaces()->save($workPlaceModel);
    }

    /**
     * Get current user region IDs.
     *
     * @param int $userId
     * @return array
     */
    public static function getCurrentUserRegionId($userId)
    {
        $user = self::find($userId);
        if (!$user) {
            return [];
        }
        
        return $user->regions()->pluck('viki_regions.id')->toArray();
    }

    /**
     * Check if user is a manager.
     *
     * @param int $userId
     * @return string
     */
    public static function isManager($userId)
    {
        $user = self::find($userId);
        if (!$user) {
            return 'no';
        }
        
        return $user->hasRole('manager') ? 'isManager' : 'no';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Panel-specific access control using Filament Shield
        switch ($panel->getId()) {
            case 'admin':
                // Admin panel access - requires admin role or explicit admin panel permission
                return $this->hasAnyRole(['admin', 'manager']) || $this->can('access_admin_panel');
                
            case 'service':
                // Service panel access - requires service panel permission
                return $this->can('access_service_panel');
                
            default:
                // Default fallback for any unknown panels
                return false;
        }
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
