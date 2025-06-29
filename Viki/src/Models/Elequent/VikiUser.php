<?php


namespace viki\Service\Models\Elequent;

use App\User;
use viki\Service\Models\Elequent\Region;
use Illuminate\Support\Facades\DB;

class VikiUser extends User
{
    public function regions()
    {
        return $this->belongsToMany(Region::class, 'viki_user_region', 'user_id', 'region_id');
    }

    public function getRegionId()
    {
        return $this->regions()->get()[0]->id;
    }

    public function workPlaces()
    {
        return $this->belongsToMany(WorkPlace::class, 'viki_supervisor_work_place', 'supervisor_id', 'work_place_id');
    }

    public function activeWorkPlaces()
    {
        return $this->belongsToMany(WorkPlace::class, 'viki_supervisor_work_place', 'supervisor_id', 'work_place_id')->where('viki_work_place.status', WorkPlace::WORK_PLACE_ACTIVE);
    }

    /**
     * Assign the given region to the user.
     *
     * @param  string $region
     *
     * @return mixed
     */
    public function assignRegion($region)
    {
        return $this->regions()->save(
            Region::whereName($region)->firstOrFail()
        );
    }

    /**
     * Assign the given work place to the user.
     *
     * @param  string $workPlace
     *
     * @return mixed
     */
    public function assignWorkPlace($workPlace)
    {
        return $this->workPlaces()->save(
            WorkPlace::whereName($workPlace)->firstOrFail()
        );
    }

    public static function getCurrentUserRegionId($userId) {
    $vikiUser = self::find($userId);
    $regions = $vikiUser->regions()->get();
    $regionsIds = [];
    foreach ($regions as $region) {
      $regionsIds[] = $region->id;
    }
    return $regionsIds;
  }

	public static function isManager($userId) {
    $managerCheck = [];
   
    $managerCheck = DB::table('role_user')->where('user_id',$userId)->where('role_id',2)->get();
   
    if(!$managerCheck->isEmpty()) {
      return 'isManager';
    }
    return 'no';
  }
}