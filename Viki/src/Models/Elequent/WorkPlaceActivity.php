<?php

namespace viki\Service\Models\Elequent;
use \Carbon\Carbon;
use \Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use viki\Service\Models\Elequent\WorkPlaceActivityHoursPerDay;

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

	/**
	 * Boot the model and set up event listeners
	 */
	protected static function booted(): void
	{
		// When deleting a permanent activity, cascade delete all monthly copies and related data
		static::deleting(function (WorkPlaceActivity $activity) {
			// Only cascade delete if this is a permanent activity (date = NULL, copied = 0)
			if ($activity->date === null && $activity->copied == self::NOT_COPIED_ACTIVITY) {
				// Find all monthly copies of this permanent activity
				$monthlyActivityIds = static::where('work_place_id', $activity->work_place_id)
					->where('activity', $activity->activity)
					->where('type_working', $activity->type_working)
					->where('copied', self::NOT_COPIED_ACTIVITY)
					->whereNotNull('date')
					->pluck('id');

				// Clean up related data for monthly activities
				if ($monthlyActivityIds->isNotEmpty()) {
					// Delete worker records
					DB::table('viki_worker_records')
						->whereIn('work_place_activity_id', $monthlyActivityIds)
						->delete();

					// Delete hours configuration by month
					DB::table('viki_hours_activity_by_month')
						->whereIn('work_place_activity_id', $monthlyActivityIds)
						->delete();

					// Delete hours per day configuration
					DB::table('viki_hours_per_day_activity')
						->whereIn('work_place_activity_id', $monthlyActivityIds)
						->delete();

					// Delete pivot table entries (worker assignments)
					DB::table('viki_work_place_activity_worker')
						->whereIn('work_place_activity_id', $monthlyActivityIds)
						->delete();

					// Finally, delete the monthly activity copies themselves
					static::whereIn('id', $monthlyActivityIds)->delete();
				}
			}
		});
	}

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
        return $this->belongsTo('App\Models\User');
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

    public function hoursPerDay()
    {
        return $this->hasOne(WorkPlaceActivityHoursPerDay::class, 'work_place_activity_id');
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
