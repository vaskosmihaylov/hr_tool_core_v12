<?php

namespace viki\Service\Models\Elequent;

use \Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    const CLIENT_ACTIVE = 0;
    const CLIENT_UNACTIVE   = 1;

    protected $table = "viki_clients";

    protected $fillable = [
        'name',
        'budget',
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
			'budget'                   => $attributes['budget'],
          
        );

        $model = new static($modelAttributes);

        $model->save();

        return $model;
    }
    /**
     * Get the workplaces for the client 
    */
    public function workplaces()
    {
        return $this->hasMany('viki\Service\Models\Elequent\WorkPlace');
    }
	
	public static function checkActiveWorkplaces($workplaces)
	{
		foreach($workplaces as $workplace){
			if($workplace->status == WorkPlace::WORK_PLACE_ACTIVE){
				return true;
			}
		}
		
		return false;
	}
		
	/**
	* Get the regions for the client 
	*/
	public function regions()
	{
		return $this->belongsToMany('viki\Service\Models\Elequent\Region','viki_client_region','client_id', 'region_id');
	}
	
	public  static function clientStatuses()
	{
		
		$statuses = array(array('id' =>self::CLIENT_ACTIVE,'name'=>'активен'),
						  array('id' =>self::CLIENT_UNACTIVE,'name'=>'неактивен')
					);
		
		return $statuses;
	}
 }
