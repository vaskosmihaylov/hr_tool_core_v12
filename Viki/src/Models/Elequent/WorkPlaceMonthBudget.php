<?php

namespace viki\Service\Models\Elequent;
use \Carbon\Carbon;
use \Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class WorkPlaceMonthBudget extends Model
{


	protected $table = 'viki_workplace_month_budget';

	protected $fillable = [
		'work_place_id',
		'created_by',
		'sum_up',
		'date',

	];

    public function createdBy()
    {
        return $this->belongsTo('App\Models\Elequent\User');
    }

    public function workplace()
    {
        return $this->belongsTo('viki\Service\Models\Elequent\WorkPlace','work_place_id');
    }
}
