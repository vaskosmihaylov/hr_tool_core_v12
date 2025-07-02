<?php

namespace viki\Service\Models\Elequent;

use \Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class WorkPlaceActivityHoursPerDay extends Model {

  protected $table = 'viki_hours_per_day_activity';
  protected $fillable = [
    'hours_per_day',
    'created_by',
    'work_place_activity_id'
  ];

  public static function create($hours_per_day, $id) {

    $model = Self::where('work_place_activity_id', $id)->first();

    if (!$model) {
      $modelAttributes = array(
        'created_by' => Auth::id(),
        'hours_per_day' => $hours_per_day,
        'work_place_activity_id' => $id,
      );

      $model = new static($modelAttributes);
      $model->save();
    }
    else {
      Self::where('work_place_activity_id', $id)->update(array('hours_per_day' => $hours_per_day));
    }
    return $model;
  }

  public function createdBy() {
    return $this->belongsTo('App\Models\User');
  }

  public function workplace() {
    return $this->belongsTo('viki\Service\Models\Elequent\WorkPlace', 'work_place_id');
  }

  public static function findHoursPerDayPerActivity($id) {
    $hours_per_day = 0;
    $hours_per_day = Self::where('work_place_activity_id', $id)->first();
    if (!empty($hours_per_day)) {
      $hours_per_day = $hours_per_day->hours_per_day;
    }
    return $hours_per_day;
  }

}
