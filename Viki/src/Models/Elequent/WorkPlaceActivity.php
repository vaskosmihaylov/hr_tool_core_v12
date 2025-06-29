<?php

namespace viki\Service\Models\Elequent;
use \Carbon\Carbon;
use \Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class WorkPlaceActivity extends Model
{

	const WORKING_STANDART   = 0;
	const WORKING_BY_HOURS   = 1;
	
	const NOT_COPIED_ACTIVITY = 0;
	const COPIED_ACTIVITY = 1;
	
	protected $table = 'viki_work_place_activity';

	protected $fillable = [
		'activity',
		'copied',
		'type_working',
		'created_by',
		'neto_salary',
		'social_plus',
		'work_place_id',
		'worker_count',
		'date'

	];

	public static function create(array $attributes  = array(),$id,$date = null)
	{	
		if(!empty($date)) {
			$attributes['date'] = $date;
			
		}else{
			$date = NULL;
		}
			$modelAttributes = array(
			'created_by'			=> Auth::id(),
			'activity'			    => $attributes['activity'],
			'neto_salary'			=> $attributes['neto_salary'],
			'social_plus'			=> $attributes['social_plus'],
			'worker_count'			=> $attributes['worker_count'],
			'type_working'			=> $attributes['type_working'],
			'work_place_id'			=> $id,
			'date'     => $date
			);

        $model = new static($modelAttributes);

        $model->save();

        return $model;
    }  
	public static function createCopied(array $attributes  = array())
	{

        $model = new static($attributes);

        $model->save();

        return $model;
    }  

    public function createdBy()
    {
        return $this->belongsTo('App\User');
    }

    public function workplace()
    {
        return $this->belongsTo('viki\Service\Models\Elequent\WorkPlace','work_place_id');
    }
	
    public function hours()
    {
        return $this->hasMany('viki\Service\Models\Elequent\HoursActivityByMonth','work_place_activity_id');
    }
	
	public function temporaryWorkers()
    {
        return $this->belongsToMany(Worker::class,'viki_work_place_activity_worker')->withPivot('date');
    }
	
	public  static function workerTypeWorking()
	{
		$typeWorking = array(array('id' =>self::WORKING_STANDART ,'name'=>'стандартно'),
								array('id' =>self::WORKING_BY_HOURS,'name'=>'сумарно')
					);
		
		return $typeWorking;
	}
	
	public static function checkIfActivitiesAreCopied($workPlaceId, $year, $month)
	{
		$workPlaceActivityByMonth = WorkPlaceActivity::where('work_place_id','=',$workPlaceId)
														->where('date','like','%' . $year."-".$month."-" . '%')	
														->get();

		if(count($workPlaceActivityByMonth) == 0)	 {
			return false;
		}
		
		return true;
	}
}
