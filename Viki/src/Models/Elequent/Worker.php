<?php

namespace viki\Service\Models\Elequent;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{
    const USER_ACTIVE   = 0;
    const USER_UNACTIVE = 1;
    const USER_DELETED  = 2;
    const USER_ILL = 3;
    const USER_PAID_LEAVE = 4;
    const USER_UNPAID_LEAVE = 5;

    const WORKING_STANDART   = 0;
    const WORKING_BY_HOURS   = 1;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'viki_workers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'middle_name',
        'family_name',
        'egn',
        'status',
        'start_date',
        'type_working',
        'hours_per_day',
        'neto_salary',
        'work_place_id',
        'region_id',
        'note',
        'unactive_from_date',
        'work_place_activity_id',
        'income'
    ];

    /**
     * @param array $attributes
     * @return static
     */
    public static function create(array $attributes = [])
    {
        $attributes['start_date'] = Carbon::parse($attributes['start_date'])->format('Y-m-d');

        if(isset($attributes['unactive_from_date'])) {
            $attributes['unactive_from_date'] = Carbon::parse($attributes['unactive_from_date'])->format('Y-m-d');
        }

        $modelAttributes = [
            'name'                     => $attributes['name'],
            'middle_name'              => $attributes['middle_name'],
            'family_name'              => $attributes['family_name'],
            'egn'                      => $attributes['egn'],
            'status'                   => $attributes['status'] ?? 0,
            'start_date'               => $attributes['start_date'],
            'type_working'             => $attributes['type_working'],
            'region_id'                => $attributes['region_id'],
            'work_place_id'            => $attributes['work_place_id'] ?? null,
            'work_place_activity_id'   => $attributes['work_place_activity_id'] ?? 0,
            'hours_per_day'            => $attributes['hours_per_day'],
            'neto_salary'              => $attributes['neto_salary'],
            'income'                   => $attributes['income'],
            'note'                     => $attributes['note'] ?? '',
            'unactive_from_date'       => $attributes['unactive_from_date'] ?? null,
        ];

        $model = new static($modelAttributes);
        $model->save();

        return $model;
    }

    public function isActive()
    {
        return ($this->status == self::USER_ACTIVE);
    }

    /**
     * Get the post that owns the client.
     */
    public function workplace()
    {
        return $this->belongsTo(WorkPlace::class, 'work_place_id');
    }

    public function temporaryWorkplace()
    {
        return $this->belongsToMany(WorkPlace::class, 'viki_work_place_worker')->withPivot('date');
    }

    public function temporaryWorkplaceActivity()
    {
        return $this->belongsToMany(WorkPlaceActivity::class, 'viki_work_place_activity_worker')->withPivot('date');
    }

    public function workPlaceActivity()
    {
        return $this->belongsTo(WorkPlaceActivity::class, 'work_place_activity_id');
    }

    public function vacations()
    {
        return $this->hasMany(Vacation::class, 'worker_id');
    }

    /**
     * Get the post that owns the region.
     */
    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function workerRecords()
    {
        return $this->hasMany(WorkerRecord::class);
    }

    public static function workerStatuses()
    {
        return [
            ['id' => self::USER_ACTIVE, 'name' => 'активен'],
            ['id' => self::USER_UNACTIVE, 'name' => 'неактивен']
        ];
    }

    public static function workerTypeWorking()
    {
        return [
            ['id' => self::WORKING_STANDART, 'name' => 'стандартно'],
            ['id' => self::WORKING_BY_HOURS, 'name' => 'сумарно']
        ];
    }

    public function bonus()
    {
        return $this->hasMany(WorkerBonus::class, 'worker_id');
    }
}
