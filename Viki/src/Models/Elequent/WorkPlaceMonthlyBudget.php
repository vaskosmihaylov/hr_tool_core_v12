<?php

namespace viki\Service\Models\Elequent;

use \Illuminate\Database\Eloquent\Model;

class WorkPlaceMonthlyBudget extends Model
{
    protected $table = "viki_workplace_monthly_budget";

    protected $fillable = [
        'budget',
        'valid_from',
        'viki_work_place_id'
    ];
	
	/**
     * @param array $attributes
     * @return static
     */
    public static function create(array $attributes  = array())
    {
        $modelAttributes = [
            'budget'                => $attributes['name'],
            'valid_from'            => $attributes ['address'] ? $attributes ['address'] : '',
            'viki_work_place_id'    => $attributes['status']
        ];

        $model = new static($modelAttributes);

        $model->save();

        return $model;
    }
    /**
     * Get the budget workplace
    */
    public function workplace()
    {
        return $this->belongsTo('viki\Service\Models\Elequent\WorkPlace', 'viki_work_place_id');
    }
 }
