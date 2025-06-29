<?php

namespace viki\Service\Models\Elequent;

use \Illuminate\Database\Eloquent\Model;

class Approvement extends Model {

  const STATUS_NEW = 0;
  const STATUS_APPROVED = 1;
  const STATUS_UNAPPROVED = 2;
  const TYPE_APPR_WORKER = 0;
  const TYPE_APPR_OBJECT = 1;
  const TYPE_APPR_CLIENT = 2;
  const TYPE_APPR_BONUS = 3;

  protected $table = "viki_approvements";
  protected $fillable = [
    'date',
    'status',
    'type_id',
    'creator_id',
    'work_place_id',
    'sum_above_budget',
    'viki_worker_bonus_id'
  ];

  public function creator() {
    return $this->belongsTo('\App\Models\User', 'creator_id');
  }

  public function workplace() {
    return $this->belongsTo('viki\Service\Models\Elequent\WorkPlace', 'work_place_id');
  }

  public static function approvementStatuses() {
    return [
      [
        'id' => self::STATUS_APPROVED,
        'name' => 'одобрен'
      ], [
        'id' => self::STATUS_UNAPPROVED,
        'name' => 'неодобрен'
      ],
      [
        'id' => self::STATUS_NEW,
        'name' => 'нов'
      ]
    ];
  }

  public static function approvementTypes() {
    return [
      [
        'id' => self::TYPE_APPR_OBJECT,
        'name' => 'надвишен бюджет на обект'
      ],
      [
        'id' => self::TYPE_APPR_CLIENT,
        'name' => 'надвишен бюджет на клиента'
      ]
    ];
  }

  public static function getapprovementStatuses($i) {

    switch ($i) {
      case self::STATUS_APPROVED:
        return "одобрен";
        break;
      case self::STATUS_UNAPPROVED:
        return "неодобрен";
        break;
      case self::STATUS_NEW:
        return "нов";
        break;
    }
  }

  public static function getApprovementBonus($bonusId) {

    $bonusApprovement = Approvement::where('viki_worker_bonus_id', '=', $bonusId)
      ->first();
  
    if (empty($bonusApprovement)) {
      return '-';
    }
    return self::getapprovementStatuses($bonusApprovement->status);
  }

}
