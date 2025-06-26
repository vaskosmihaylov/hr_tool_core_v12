<?php

namespace viki\Service\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use viki\Service\Models\Elequent\Archive;
use viki\Service\Models\Elequent\VikiUser;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Traits\PresenceTableTrait;
use Carbon\Carbon;

class ArchiveController extends Controller
{
  use AuthorizesRequests, ValidatesRequests, PresenceTableTrait;

  public function index($workPlaceId = null, $date = null)
  {
    // Set default values if none provided
    if (is_null($workPlaceId)) {
      $workPlaceId = 11;
    }

    if (is_null($date)) {
      $date = '03-2024';
    }

    $user = VikiUser::find(Auth::user()->id);

    if (Auth::user()->hasRole('manager')) {
      $region_id = VikiUser::getCurrentUserRegionId(Auth::user()->id);
      $userWorkPlacesObj = WorkPlace::where('status', WorkPlace::WORK_PLACE_ACTIVE)
        ->whereIn('viki_work_place.region_id', $region_id)
        ->orderBy('name')
        ->get();
    } else if (Auth::user()->hasRole('supervisor')) {
      $userWorkPlacesObj = $user->workPlaces()->orderBy('name', 'ASC')->get();
    } else if (Auth::user()->hasRole('admin')) {
      $userWorkPlacesObj = WorkPlace::orderBy('name', 'ASC')->get();
    }

    $userWorkPlaceArray = [
      'ids' => []
    ];

    foreach ($userWorkPlacesObj as $userWorkPlace) {
      $userWorkPlaceArray['ids'][] = $userWorkPlace->id;
      $userWorkPlaceArray[$userWorkPlace->id] = $userWorkPlace;
    }

    // Calculate the date range for the past year
    $oneYearAgo = Carbon::now()->subYear()->startOfDay();
    $today = Carbon::now()->endOfDay();

    // Retrieve archives within the last year
    $archives = Archive::whereIn('work_place_id', $userWorkPlaceArray['ids'])
      ->whereBetween('date', [$oneYearAgo, $today])
      ->get();

    if ($archives->count() < 1) {
      return view('service::archive.404');
    }

    foreach ($archives as $archive) {
      $userWorkPlaces[$archive->work_place_id] = $userWorkPlaceArray[$archive->work_place_id]->name;
    }

    if ($workPlaceId) {
      if (!array_key_exists($workPlaceId, $userWorkPlaces)) {
        return view('service::archive.404');
      }

      $selectedWorkPlace = WorkPlace::find($workPlaceId);
      $workPlaceId = $selectedWorkPlace->id;
    } else {
      $selectedWorkPlace = WorkPlace::find($archives->first()->work_place_id);
      $workPlaceId = $selectedWorkPlace->id;
    }

    if ($date) {
      $selectedArchive = null;

      foreach ($archives as $archive) {
        if ($archive->work_place_id == $workPlaceId && $archive->date === date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-d')) {
          $selectedArchive = $archive;
          break;
        }
      }

      if (!$selectedArchive) {
        return view('service::archive.404');
      }
    } else {
      $selectedArchive = null;

      foreach ($archives as $archive) {
        if ($archive->work_place_id == $workPlaceId) {
          $selectedArchive = $archive;
          break;
        }
      }

      if (!$selectedArchive) {
        return view('service::archive.404');
      }
    }

    $datesForCurrentArchive = DB::table('viki_archive')
      ->where('work_place_id', $workPlaceId)
      ->whereBetween('date', [$oneYearAgo, $today])
      ->select('date')
      ->groupBy('date')
      ->get();

    $availableMonths = [];

    foreach ($datesForCurrentArchive as $obj) {
      $availableMonths[] = date('m-Y', strtotime($obj->date));
    }

    $date = date('m-Y', strtotime($selectedArchive->date));
    $selectedDate = $date;

    $arr = explode("-", $date, 2);
    $month = $arr[0];
    $year = $arr[1];

    $weekDays = $this->getAllNonWorkingDays($month, $year);
    $monthDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    $availableMonths = array_combine($availableMonths, $availableMonths);

    $tableNotEditable = true;

    $tableData = json_decode($selectedArchive->json_data, true);

    foreach ($tableData as $key => $tableDatum) {
      foreach ($tableDatum['workPlaceActivityWorkers'] as $workerKey => $workPlaceActivityWorker) {
        foreach ($workPlaceActivityWorker['worker_records'] as $recordKey => $worker_record) {
          $tableData[$key]['workPlaceActivityWorkers'][$workerKey]['worker_records'][$recordKey] = (object)$tableData[$key]['workPlaceActivityWorkers'][$workerKey]['worker_records'][$recordKey];
        }

        foreach ($workPlaceActivityWorker['vacations'] as $vacationKey => $vacation) {
          $tableData[$key]['workPlaceActivityWorkers'][$workerKey]['vacations'][$vacationKey] = (object)$tableData[$key]['workPlaceActivityWorkers'][$workerKey]['vacations'][$vacationKey];
        }

        $tableData[$key]['workPlaceActivityWorkers'][$workerKey] = (object)$tableData[$key]['workPlaceActivityWorkers'][$workerKey];
      }
    }

    return view('service::archive.index', compact('weekDays', 'monthDays', 'tableData', 'selectedWorkPlace', 'userWorkPlaces', 'workPlaceId', 'availableMonths', 'selectedDate', 'tableNotEditable'));
  }
}
