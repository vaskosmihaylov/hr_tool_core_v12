<?php

namespace viki\Service\Models\Elequent;
use \Carbon\Carbon;
use \Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class HoursActivityByMonth extends Model
{

	const WORKING_STANDART   = 0;
	const WORKING_BY_HOURS   = 1;

	protected $table = 'viki_hours_activity_by_month';

	protected $fillable = [
		'work_place_activity_id',	
		'created_by',
		'hours_for_person',
		'date',

	];

	public static function create($hours, $id , $date)
	{
			$modelAttributes = array(
			'created_by'			=> Auth::id(),
			'hours_for_person'		=> $hours,
			'date'					=> $date,
			'work_place_activity_id' => $id
			);

        $model = new static($modelAttributes);

        $model->save();

        return $model;
    }
	
	public static function createFromConfig($attributes)
	{
        $model = new static($attributes);

        $model->save();

        return $model;
    } 
	
    public function createdBy()
    {
        return $this->belongsTo('App\Models\Elequent\User');
    }

    public function workplaceActivity()
    {
        return $this->belongsTo('viki\Service\Models\Elequent\WorkPlaceActivity','work_place_activity_id');
    }
}
