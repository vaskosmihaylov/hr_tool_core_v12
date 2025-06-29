<?php

namespace viki\Service\Models\Elequent;

use \Illuminate\Database\Eloquent\Model;

class WorkPlace extends Model
{
    const WORK_PLACE_ACTIVE = 0;
    const WORK_PLACE_UNACTIVE   = 1;
    
    protected $table = "viki_work_place";

    protected $fillable = [
        'name',
        'budget',
        'address',
        'status',
        'client_id',
        'region_id',
       
    ];
	
	/**
     * @param array $attributes
     * @return static
     */
    public static function create(array $attributes  = array())
    {
        $modelAttributes = array(

            'name'                     => $attributes['name'],
            'address'                  => $attributes ['address'] ? $attributes ['address'] : '',
            'status'                   => $attributes['status'],
            'client_id'                => $attributes['client'],
            'region_id'                => $attributes['region'],
            'budget'                   => $attributes['budget'],
          
        );

        $model = new static($modelAttributes);

        $model->save();

        return $model;
    }
    /**
     * Get the post that owns the client.
    */
    public function client()
    {
        return $this->belongsTo('viki\Service\Models\Elequent\Client','client_id');
    }
    
    /**
     * Get the post that owns the region.
    */
    public function region()
    {
        return $this->belongsTo('viki\Service\Models\Elequent\Region','region_id');
    }

    /**
     * A permission can be applied to workers
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
    */
    public function workers()
    {
        return $this->belongsToMany(Worker::class, 'viki_work_place_worker', 'work_place_id', 'worker_id');
    }
	
	public function temporaryWorkers()
    {
        return $this->belongsToMany(Worker::class,'viki_work_place_worker')->withPivot('date');
    }
    /**
     * A permission can be applied to supervisiors
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
    */
    public function supervisiors()
    {
        return $this->belongsToMany(\App\Models\User::class);
    }

    public function overBudget()
    {
        return $this->hasMany(WorkPlaceMonthBudget::class, 'work_place_id');
    }
    
    /**
     *  workers records
     *
     * @return \Illuminate\Database\Eloquent\Relations\OneToMany
    */
    public function workerRecords()
    {
        return $this->hasMany(WorkerRecord::class);
    }
	
	public static function getActiveWorkPlaces()
	{
		$workplaces = WorkPlace::where('status','=', WorkPlace::WORK_PLACE_ACTIVE)->get();
		
		return $workplaces;
	}
	
	public  static function workPlaceStatuses()
	{
        return [
            [
                'id' => self::WORK_PLACE_ACTIVE,
                'name'=>'активен'
            ], [
                'id' => self::WORK_PLACE_UNACTIVE,
                'name'=>'неактивен'
            ]
        ];
    }
	  
	public function getBudgetByDate($date)
    {
        $budgetTimestamp = \DateTime::createFromFormat('d-m-Y H:i:s', '01-' . $date . ' 00:00:00')->getTimestamp();

        $budget = $this->monthlyBudget()->orderBy('valid_from', 'desc')->where('valid_from', '<=', $budgetTimestamp)->first();

        return $budget ? $budget->budget : $this->budget;
    }
	
	public function monthlyBudget()
    {
        return $this->hasMany(WorkPlaceMonthlyBudget::class, 'viki_work_place_id');
    }
	
	 public function getWorkerBonusesByDate($date)
    {
        $workersBonuses = 0;

        $bonusTimestamp = \DateTime::createFromFormat('d-m-Y H:i:s', '01-' . $date . ' 00:00:00')->getTimestamp();

        foreach ($this->bonus->where('for_month', $bonusTimestamp) as $bonus) {
            if ($bonus->type == WorkerBonus::$typeBonus) {
                $workersBonuses += $bonus->sum;
            } else {
                $workersBonuses -= $bonus->sum;
            }
        }

        return $workersBonuses;
    }

    public function getBudgetByDateWithWorkerBonuses($date)
    {
        return $this->getBudgetByDate($date) + $this->getWorkerBonusesByDate($date);
    }

    public function bonus()
    {
        return $this->hasMany(WorkerBonus::class);
    }

    public function activities()
    {
        return $this->hasMany(WorkPlaceActivity::class, 'work_place_id');
    }

 }

use viki\Service\Models\Elequent\WorkPlaceActivity;
