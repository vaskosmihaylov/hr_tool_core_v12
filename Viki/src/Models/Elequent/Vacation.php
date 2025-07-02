<?php

namespace viki\Service\Models\Elequent;
use \Carbon\Carbon;
use \Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Vacation extends Model
{

    const PAYD_VACATION         = 1;
    const NOT_PAYD_VACATION     = 2;
    const HOSPITAL_SHEET        = 3;

    protected $table = 'viki_vacations';

    protected $fillable = [
        'worker_id',
        'type',
		'created_by',
        'start_date',
        'end_date',
        'comment',
        'day_count',
        'status'
    ];

    public static function create(array $attributes  = array())
    {
		
        $attributes['start_date'] = Carbon::parse($attributes['start_date'])->format('Y-m-d');
		$attributes['end_date'] = Carbon::parse( $attributes['end_date'])->format('Y-m-d');
        $modelAttributes = array(
            'created_by'    => Auth::id(),
            'type'          => $attributes['type'],
            'start_date'    => $attributes['start_date'],
            'end_date'      => $attributes['end_date'],
            'comment'       => isset($attributes['comment']) ? $attributes['comment'] : '' ,
            'day_count'     => 1 ,
            'worker_id'     => $attributes['worker_id'] ,
             'status' => 1
        );

        $model = new static($modelAttributes);

        $model->save();

        return $model;
    }  

    public function createdBy()
    {
        return $this->belongsTo('App\Models\User','created_by');
    }
	
    public function worker()
    {
        return $this->belongsTo('viki\Service\Models\Elequent\Worker','worker_id');
    }
	
	public  static function vacationStatuses()
	{
		
		$statuses = array(array('id' =>self::PAYD_VACATION,'name'=>'платена отпуска'),
						array('id' =>self::NOT_PAYD_VACATION,'name'=>'неплатена отпуска'),
						  array('id' =>self::HOSPITAL_SHEET,'name'=>'болничен')
					);
		
		return $statuses;
	}

    
}
