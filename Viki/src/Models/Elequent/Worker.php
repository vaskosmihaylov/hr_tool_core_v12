<?php

namespace viki\Service\Models\Elequent;
use \Carbon\Carbon;
use \Illuminate\Database\Eloquent\Model;


class Worker extends Model
{  
    const USER_ACTIVE   = 0;
    const USER_UNACTIVE = 1;
    const USER_DELETED  = 2;
    const USER_ILL = 3;
    const USER_PAID_LEAVE = 4;
	const USER_UNPAID_LEAVE = 5;

    const WORKING_STANDART   = 0;
    const WORKING_BY_HOURS   = 1;
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'viki_workers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'middle_name', 
        'family_name',
        'egn',
        'status',
        'position',
        'date_start_job',
        'date_end_job', 
        'basic_salary',
        'additional_salary',
        'working_time',
        'type_working',
        'work_place_id',
        'region_id',
        'work_place_activity_id',
        'note',
        // Legacy fields for backward compatibility
        'start_date',
        'hours_per_day',
        'neto_salary',
        'unactive_from_date',
        'income'
    ];

    /**
     * @param array $attributes
     * @return static
     */
    public static function create(array $attributes  = array())
    {
        $attributes['start_date'] = Carbon::parse($attributes['start_date'] ?? Carbon::now())->format('Y-m-d');
			
			if(isset($attributes['unactive_from_date']) && !empty($attributes['unactive_from_date'])) {
				$attributes['unactive_from_date'] = Carbon::parse($attributes['unactive_from_date'])->format('Y-m-d');
			}
			
        $modelAttributes = array(

            'name'                     => $attributes['name'] ?? null,
            'middle_name'              => $attributes['middle_name'] ?? null,
            'family_name'              => $attributes['family_name'] ?? null,
            'egn'                      => $attributes['egn'] ?? null,
            'status'                   => isset($attributes['status']) ? $attributes['status']:0,
            'start_date'               => $attributes['start_date'],
            'type_working'             => $attributes['type_working'] ?? self::WORKING_BY_HOURS,
            'region_id'                => isset($attributes['region_id']) && $attributes['region_id'] !== '' ? $attributes['region_id'] : 0,
            'work_place_id'            => isset($attributes['work_place_id']) && $attributes['work_place_id'] !== '' ? $attributes ['work_place_id'] : 0,
            'work_place_activity_id'   => isset($attributes['work_place_activity_id']) ? $attributes ['work_place_activity_id'] : 0,
            'hours_per_day'            => isset($attributes['hours_per_day']) && $attributes['hours_per_day'] !== '' ? $attributes['hours_per_day'] : 8,
            'neto_salary'              => isset($attributes['neto_salary']) && $attributes['neto_salary'] !== '' ? $attributes['neto_salary'] : 0,
				  'income'              => isset($attributes['income']) && $attributes['income'] !== '' ? $attributes['income'] : 0,
            'note'                     => isset($attributes['note']) && $attributes['note'] !== '' ? $attributes['note'] : null,
            'unactive_from_date'       => isset($attributes['unactive_from_date'])? $attributes['unactive_from_date'] : null,
        );

        $model = new static($modelAttributes);

        $model->save();

        return $model;
    }

    public function isActive()
    {
        return ($this->status == self::USER_ACTIVE ) ? true : false;
    }

    /**
     * Get the post that owns the client.
    */
    public function workplace()
    {
        return $this->belongsTo('viki\Service\Models\Elequent\WorkPlace','work_place_id');
    }
   
	public function temporaryWorkplace()
    {
        return $this->belongsToMany(WorkPlace::class,'viki_work_place_worker')->withPivot('date');
    }
	
	public function temporaryWorkplaceActivity()
    {
        return $this->belongsToMany(WorkPlaceActivity::class,'viki_work_place_activity_worker')->withPivot('date');
    }
	
    public function workPlaceActivity()
    {
        return $this->belongsTo('viki\Service\Models\Elequent\WorkPlaceActivity','work_place_activity_id');
    }

    public function vacations()
    {
        return $this->hasMany(Vacation::class, 'worker_id');
    }
	
    /**
     * Get the post that owns the region.
    */
    public function region()
    {
        return $this->belongsTo('viki\Service\Models\Elequent\Region','region_id');
    }
	
    public function workerRecords()
    {
        return $this->hasMany(WorkerRecord::class);
    }
	
	public  static function workerStatuses()
	{
		
		$statuses = array(array('id' =>self::USER_ACTIVE,'name'=>'активен'),
						  array('id' =>self::USER_UNACTIVE,'name'=>'неактивен')
					);
		
		return $statuses;
	}
	
	public  static function workerTypeWorking()
	{		
		$typeWorking = array(array('id' =>self::WORKING_STANDART ,'name'=>'стандартно'),
						  array('id' =>self::WORKING_BY_HOURS,'name'=>'сумарно')
					);
		
		return $typeWorking;
	}
	
	public function bonus()
    {
        return $this->hasMany(WorkerBonus::class, 'worker_id');
    }

    // New relationships and methods for Filament compatibility
    public function bonuses()
    {
        return $this->hasMany(WorkerBonus::class, 'worker_id');
    }

    // Updated constants for Filament (using existing constants)
    const WORKER_ACTIVE = 0;  // Same as USER_ACTIVE
    const WORKER_INACTIVE = 1;  // Same as USER_UNACTIVE
	
}
