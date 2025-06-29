<?php

namespace viki\Service\Models\Elequent;

use \Illuminate\Database\Eloquent\Model;

class WorkerBonus extends Model {

  static $typeBonus = 0;
  static $typePayCut = 1;

  const BONUS = 0;
  const PAY_CUT = 1;

  protected $table = "viki_worker_bonus";
  protected $fillable = [
    'sum',
    'type',
    'for_month',
    'work_place_id',
    'worker_id'
  ];

  /**
   * @param array $attributes
   * @return static
   */
  public static function create(array $attributes = array()) {
    $modelAttributes = [
      'sum' => $attributes['sum'],
      'type' => $attributes['type'],
      'for_month' => $attributes['for_month'],
      'work_place_id' => $attributes['work_place_id'],
      'worker_id' => $attributes['worker_id'],
    ];

    $model = new static($modelAttributes);

    $model->save();

    return $model;
  }

  /**
   * Get the budget workplace
   */
  public function workplace() {
    return $this->belongsTo('viki\Service\Models\Elequent\WorkPlace', 'work_place_id');
  }

  /**
   * Get the budget workplace
   */
  public function worker() {
    return $this->belongsTo('viki\Service\Models\Elequent\Worker', 'worker_id');
  }

  public static function getBonusCutByMonth($workPlaceId, $workerId, $date, $type) {
    $dates = explode('-', $date);
   
    $sumF = 0;
	$workPlaceBonuyByMonth = array();
	if ((isset($dates[0])) && (isset($dates[1]))) {
    $workPlaceBonuyByMonth = WorkerBonus::where('work_place_id', '=', $workPlaceId)
      ->where('worker_id', $workerId)
      ->where('for_month', 'like', '%' . $dates[0] . "-" . $dates[1] . "-" . '%')
      ->where('type', $type)
      ->get();
    }
    if (count($workPlaceBonuyByMonth) == 0) {
      return 0;
    }

    foreach($workPlaceBonuyByMonth as $sum) {
      //print_r($sum);
      if ($type == self::BONUS ) {
        $status = Approvement::getApprovementBonus($sum->id);
        //print_r($status);
        if (($status == '-') || ($status == 'одобрен')){
            $sumF = $sumF + $sum->sum;
        }
      } else {
        $sumF = $sumF + $sum->sum;
      }
    }
    return $sumF;
  }

}
