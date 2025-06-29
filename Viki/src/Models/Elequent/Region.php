<?php

namespace viki\Service\Models\Elequent;

use \Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    const REGION_ACTIVE = 0;
    const REGION_UNACTIVE   = 1;

    protected $table = "viki_regions";

    protected $fillable = [
        'name',
        'status',
    ];
	
	/**
     * @param array $attributes
     * @return static
     */
    public static function create(array $attributes  = array())
    {      		
        $modelAttributes = array(
            'name'                     => $attributes['name'],
            'status'                   => $attributes['status'],
        );

        $model = new static($modelAttributes);

        $model->save();

        return $model;
    }
	
	/**
	* Get the clients for the region.
	*/
	public function clients()
	{
		return $this->belongsToMany('viki\Service\Models\Elequent\Client','viki_client_region');
	}
	/**
	* Get the MANAGER for the region.
	*/
	public function managers()
	{
		 return $this->belongsToMany('viki\Service\Models\Elequent\VikiUser', 'viki_user_region', 'region_id', 'user_id');
	}
	
    /**
     * Get the objects for the region.
    */
    public function workplace()
    {
        return $this->hasMany('viki\Service\Models\Elequent\WorkPlace');
    }

    /**
     * Get the objects for the region.
     */
    public function activeWorkPlaces()
    {
        return $this->hasMany('viki\Service\Models\Elequent\WorkPlace')->where('status', WorkPlace::WORK_PLACE_ACTIVE);
    }
	
	public static function regionStatuses()
    {
        return [
            self::REGION_ACTIVE => 'Активен',
            self::REGION_UNACTIVE => 'Неактивен'
        ];
    }

    // Legacy method for backward compatibility
    public static function regionStatusesOld()
    {
        return [
            [
                'id' => self::REGION_ACTIVE,
                'name'=>'активен'
            ], [
                'id' => self::REGION_UNACTIVE,
                'name'=>'неактивен'
            ]
        ];
    }

    // New relationships for Filament compatibility
    public function workers()
    {
        return $this->hasMany('viki\Service\Models\Elequent\Worker', 'region_id');
    }

    public function workplaces()
    {
        return $this->hasMany('viki\Service\Models\Elequent\WorkPlace', 'region_id');
    }
}
