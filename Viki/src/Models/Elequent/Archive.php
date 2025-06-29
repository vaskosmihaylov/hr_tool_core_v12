<?php

namespace viki\Service\Models\Elequent;

use \Illuminate\Database\Eloquent\Model;

class Archive extends Model
{
    public $timestamps = false;

    protected $table = 'viki_archive';

	protected $fillable = [
		'work_place_id',
		'date',
		'json_data'
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
