<?php

namespace viki\Service\Models\Elequent;

use \Illuminate\Database\Eloquent\Model;


class WorkerRecord extends Model
{  
    const WORKER_RECORD_WAITING		= 0;
    const WORKER_RECORD_APPROVED	= 1;
    const WORKER_RECORD_DISAPPROVED	= 2;
	const WORKER_RECORD_FINISHED	= 3; 

    public $timestamps = true;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'viki_worker_records';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = array(

        'worker_id',
        'work_place_id',
        'work_place_activity_id',
        'date',
        'hours',
        'start_date',
        'end_date',
        'day_count',
        'status',
        'approvement_id',
        'creator_id',
        'old_value'
      
    );

    public function isApproved()
    {
        return ($this->status == self::WORKER_RECORD_APPROVED ) ? true : false;
    }

    public function worker()
    {
        return $this->belongsTo('viki\Service\Models\Elequent\Worker', 'worker_id');
    }

    public function workplace()
    {
        return $this->belongsTo('viki\Service\Models\Elequent\WorkPlace', 'work_place_id');
    }
    
    public function activity()
    {
        return $this->belongsTo('viki\Service\Models\Elequent\WorkPlaceActivity', 'work_place_activity_id');
    }
	
    public function creator()
    {
         return $this->belongsTo('App\Models\User', 'creator_id');
    }

	public  static function WorkerRecordStatuses()
	{
		
		$statuses = array(array('id' =>self::WORKER_RECORD_APPROVED,'name'=>'активен'),
						  array('id' =>self::WORKER_RECORD_FINISHED,'name'=>'приключен')
					);
		
		return $statuses;
	}
}
