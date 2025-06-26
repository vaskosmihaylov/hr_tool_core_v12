<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Resource extends Model
{
    use LogsActivity;

    const RESOURCE_TYPE_RELATIVE = 1;
    const RESOURCE_TYPE_ABSOLUTE = 2;

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['type', 'value', 'permission_id'];

    /**
     * A role may be given various permissions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * Grant the given permission to a resource.
     *
     * @param  Permission $permission
     *
     * @return mixed
     */
    public function givePermissionTo(Permission $permission)
    {
        return $this->permission()->associate($permission);
    }

    /**
     * Activity log configuration.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'value', 'permission_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
