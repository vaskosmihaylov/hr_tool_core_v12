<?php

namespace viki\Service\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use viki\Service\Models\Elequent\Client;
use viki\Service\Models\Elequent\Region;
use viki\Service\Models\Elequent\VikiUser;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\Worker;
use viki\Service\Models\Elequent\WorkerRecord;
use viki\Service\Models\Elequent\WorkPlaceActivity;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use \Illuminate\Support\Facades\Redirect;
use viki\Service\Models\Elequent\SpecialDay;
use DB;
use PDF;

class ReportController extends Controller {

  public function index(Request $request) {
    $months = [
      [
        'id' => '01',
        'name' => 'Ян'], ['id' => '02', 'name' => 'Фев'], ['id' => '03', 'name' => 'Мар'],
      ['id' => '04', 'name' => 'Апр'], ['id' => '05', 'name' => 'Май'], ['id' => '06', 'name' => 'Юни'],
      ['id' => '07', 'name' => 'Юли'], ['id' => '08', 'name' => 'Авг'], ['id' => '09', 'name' => 'Сеп'], ['id' => 10, 'name' => 'Окт'], ['id' => 11, 'name' => 'Ное'], ['id' => 12, 'name' => 'Дек']
    ];
    $years = [['id' => 2022], ['id' => 2021], ['id' => 2020], ['id' => 2023], ['id' => 2024],
      ['id' => 2025], ['id' => 2026], ['id' => 2027], ['id' => 2028], ['id' => 2029],
      ['id' => 2030], ['id' => 2031], ['id' => 2032], ['id' => 2033], ['id' => 2034],
      ['id' => 2035], ['id' => 2036], ['id' => 2037], ['id' => 2038], ['id' => 2039], ['id' => 2040]];
    $month_id = 'novalue';
    $year_id = $request->get('year_id');
    $month_id = $request->get('month_id');
    //get the current month if not choossen
    if ($month_id == null) {
      $month_id = date('m');
    }

    if ($request->has('workers')) {
      $egn = null;
      if ($request->has('egn')) {
        $egn = trim($request->get('egn'));
      }
      return $this->getReportForWorkersByMonthYear($month_id, $year_id, $years, $months, $egn);
    }
    else {
      $workplace_id = [];
      if (!empty($request->get('workplace_id'))) {
        if (is_array($request->get('workplace_id'))) {
          $workplace_id = array_values($request->get('workplace_id'));
        } else {
          $workplace_id[] = $request->get('workplace_id');
        }
      }
      $region_id = [];
      if ((!empty($request->get('region_id'))) && ($request->get('region_id') != 'none')) {
        $region_id = array_values($request->get('region_id'));
      }
      $manRegion_id = '';
      $client_id = [];
      if (!empty($request->get('client_id'))) {
        $client_id = array_values($request->get('client_id'));
      }
      $worker_id = '';
      if (!empty($request->get('worker_id'))) {
        $worker_id = array_values($request->get('worker_id'));
      }

      if ((Auth::user()->hasRole('manager')) || (Auth::user()->hasRole('supervisor'))) {

        $manRegion_id = VikiUser::getCurrentUserRegionId(Auth::user()->id);
        $region_id = $manRegion_id;
      }

      $workerRecorods = array();
      $arraySum = array();
      if (!empty($request->all())) {

        if (($month_id == null) || ($month_id == 'novalue')) {
          return redirect()->route('service.reports')->withErrors(['Избери месеца!']);
        }

        if (($year_id == null) || ($year_id == 'novalue')) {
          $year_id = date("Y");
        }
        $query = WorkerRecord::select
              ('viki_worker_records.worker_id', 'viki_workers.name', 'viki_worker_records.id as ID', 'viki_workers.family_name', 'viki_workers.middle_name', 'viki_workers.egn', 'viki_worker_records.work_place_id', 'viki_worker_records.work_place_activity_id', 'viki_work_place.name as workPlaceName', 'viki_work_place.client_id as clId', 'viki_work_place.region_id as regId', 'viki_work_place_activity.neto_salary as neto_salary', 'viki_work_place_activity.social_plus as social_plus', 'viki_work_place_activity.id as actId', 'viki_work_place_activity.activity as activity', 'viki_work_place_activity.date as selectedDate', DB::raw('sum(viki_worker_records.hours) as total'), DB::raw('group_concat( DISTINCT viki_worker_records.work_place_activity_id) as activities'))
            ->leftJoin('viki_workers', function($join) {
              $join->on('viki_workers.id', '=', 'viki_worker_records.worker_id');
            })
            ->rightJoin('viki_work_place_activity', function($join) {
              $join->on('viki_work_place_activity.id', '=', 'viki_worker_records.work_place_activity_id');
            })
            ->leftJoin('viki_work_place', function($join) {
              $join->on('viki_work_place.id', '=', 'viki_worker_records.work_place_id');
            })->where('viki_worker_records.date', 'like', $year_id . '-' . $month_id . '%');

        if (Auth::user()->hasRole('supervisor')) {

          $query->where(function ($query) use ($workplace_id) {
            $query->whereIn('viki_worker_records.work_place_id', $workplace_id);
          });
        }
        if (($workplace_id != null) && ($workplace_id != 'novalue')) {
          $query->where(function ($query) use ($workplace_id) {
            $query->whereIn('viki_worker_records.work_place_id', $workplace_id);
          });
        }

        if (!empty($region_id)) {
          if (!empty($manRegion_id)) {
            $region_id = $manRegion_id;
          }
          $query->where(function ($query) use ($region_id) {
            $query->whereIn('viki_work_place.region_id', $region_id);
          });
        }
        if (!empty($client_id)) {
          $query->where(function ($query) use ($client_id) {
            $query->whereIn('viki_work_place.client_id', $client_id);
          });
        }

        if (($worker_id != null) && ($worker_id != 'novalue')) {
          $query->where(function ($query) use ($worker_id) {
            $query->where('viki_worker_records.worker_id', '=', $worker_id);
          });
        }

        $query->groupBy('viki_worker_records.worker_id', 'viki_worker_records.work_place_id');
        $workerRecorods = $query->paginate(35);

        $newSumArray = array();
        $arraySum = array();
        foreach ($workerRecorods as $records) {
          $activitiesArray = explode(",", $records->activities);

          foreach ($activitiesArray as $activity) {

            $workplaceActivity = WorkPlaceActivity::find($activity);
            //dd($workplaceActivity);
			if($workplaceActivity !== null) {
				$workingHours = self::getActivityWorkingHoursForDate($workplaceActivity, $year_id . '-' . $month_id);

				$workPlaceActivityHourPrice = $workingHours != 0 ? ($workplaceActivity->neto_salary + $workplaceActivity->social_plus) / $workingHours : 0;
				$hoursByActivity = WorkerRecord::select('viki_worker_records.work_place_activity_id', DB::raw('sum(viki_worker_records.hours) as totalHours'))->where('worker_id', $records->worker_id)
					->where('work_place_activity_id', $workplaceActivity->id)
					->where('date', 'LIKE', $year_id . '-' . $month_id . '%')->get()->toArray();
				$arraySum[$records->ID][] = $workPlaceActivityHourPrice * $hoursByActivity[0]['totalHours'];
			}	
          }
          if (!empty($arraySum)) {
            foreach ($arraySum as $key => $allSum) {
              $newSumArray[$key] = array_sum($allSum);
            }
          }
        }
      }
      if (empty($newSumArray)) {
        $newSumArray = array();
      }
      if (!empty($manRegion_id)) {
        if (Auth::user()->hasRole('supervisor')) {
          $user = VikiUser::find(Auth::user()->id);
          $workplaces = $user->workPlaces()->orderBy('name', 'ASC')->get();
        }
        else {
          $workplaces = WorkPlace::whereIn('region_id', $region_id)->orderBy('name', 'ASC')->get();
        }
      }
      else {
        $workplaces = WorkPlace::orderBy('name', 'ASC')->get();
      }
      $allRegionsForClient = array();
      $newArr = [];
      if (!empty($manRegion_id)) {
        $clients = Client::with('workplaces')->whereHas('regions', function ($q) use ($region_id) {
            $q->whereIn('id', $region_id);
          })
          ->orderBy('name', 'ASC')
          ->get();
      }
      else {
        $clients = Client::with('regions', 'workplaces')->orderBy('name', 'ASC')->get();
      }

      foreach ($clients as $dataForClients) {
        foreach ($dataForClients->regions as $reg) {

          $allRegionsForClient[$dataForClients->id][] = $reg->id;
        }
      }

      foreach ($allRegionsForClient as $key => $arrayreg) {
        $newArr[$key] = implode(',', $arrayreg);
      }

      if (!empty($manRegion_id)) {
        //$regions = Region::whereIn('id', $region_id)->orderBy('name', 'ASC')->get();
       // $workers = Worker::whereIn('region_id', $region_id)->orderBy('name', 'ASC')->orderBy('family_name', 'ASC')->get();
	    $regions = Region::whereIn('id', $manRegion_id)->orderBy('name', 'ASC')->get();
        $workers = Worker::whereIn('region_id', $manRegion_id)->orderBy('name', 'ASC')->orderBy('family_name', 'ASC')->get();
      }
      else {
        $regions = Region::orderBy('name', 'ASC')->get();
        $workers = Worker::orderBy('name', 'ASC')->orderBy('family_name', 'ASC')->get();
      }
      if (!empty($request->all()) && (!empty($month_id))) {
        //история
        activity()
          ->performedOn(Auth::user())
          ->causedBy(Auth::user())
          ->withProperties(['customProperty' => 'customValue'])
          ->log('пусната обща справка за месец ' . $month_id . ' за година ' . $year_id);
      }

      return view('service::report.index', [
        'clients' => $clients,
        'regions' => $regions,
        'workplaces' => $workplaces,
        'workers' => $workers,
        'workplace_id' => $workplace_id,
        'months' => $months,
        'allRegionsForClient' => $newArr,
        'workerRecorods' => $workerRecorods,
        'month_id' => $month_id,
        'workplace_id' => $workplace_id,
        'region_id' => $region_id,
        'client_id' => $client_id,
        'year_id' => $year_id,
        'years' => $years,
        'worker_id' => $worker_id,
        'arraySum' => $newSumArray,
      ]);
    }
  }

  public function viewWorkerPlaceReport(Request $request) {
    $months = [
      [
        'id' => '01',
        'name' => 'Ян'], ['id' => '02', 'name' => 'Фев'], ['id' => '03', 'name' => 'Мар'],
      ['id' => '04', 'name' => 'Апр'], ['id' => '05', 'name' => 'Май'], ['id' => '06', 'name' => 'Юни'],
      ['id' => '07', 'name' => 'Юли'], ['id' => '08', 'name' => 'Авг'], ['id' => '09', 'name' => 'Сеп'], ['id' => 10, 'name' => 'Окт'], ['id' => 11, 'name' => 'Ное'], ['id' => 12, 'name' => 'Дек']
    ];
    $years = [['id' => 2020], ['id' => 2021], ['id' => 2022], ['id' => 2023], ['id' => 2024],
      ['id' => 2025], ['id' => 2026], ['id' => 2027], ['id' => 2028], ['id' => 2029],
      ['id' => 2030], ['id' => 2031], ['id' => 2032], ['id' => 2033], ['id' => 2034],
      ['id' => 2035], ['id' => 2036], ['id' => 2037], ['id' => 2038], ['id' => 2039], ['id' => 2040]];
    $month_id = 'novalue';
    $year_id = $request->get('year_id');
    $status_id = $request->get('status_id');
    $month_id = $request->get('month_id');
    $region_id = null;
    if ($month_id == null) {
      $month_id = date('m');
    }
    if ($year_id == null) {
      $year_id = date('Y');
    }
    $workplaceStatuses = WorkerRecord::WorkerRecordStatuses();
    //dd($request->all());
    if ((Auth::user()->hasRole('manager')) || (Auth::user()->hasRole('supervisor'))) {

      $region_id[] = VikiUser::getCurrentUserRegionId(Auth::user()->id);
    }

    $workplaces = array();
    if (Auth::user()->hasRole('supervisor')) {
      $user = VikiUser::find(Auth::user()->id);
      $workplaces = $user->workPlaces()->get();
    }
    $workplaceIds = array();
    if (!empty($workplaces)) {
      foreach ($workplaces as $workplace) {
        $workplaceIds[] = $workplace->id;
      }
    }
    $query = WorkerRecord::select('viki_worker_records.id as ID', 'viki_worker_records.status', 'viki_worker_records.work_place_id', 'viki_worker_records.work_place_activity_id', 'viki_work_place.name as workPlaceName'
        )
        ->leftJoin('viki_work_place', function($join) {
          $join->on('viki_work_place.id', '=', 'viki_worker_records.work_place_id');
        })->where('viki_worker_records.date', 'like', $year_id . '-' . $month_id . '%');
    if (($status_id != null) && ($status_id != 'novalue')) {
      $query->where(function ($query) use ($status_id) {
        $query->where('viki_worker_records.status', '=', $status_id);
      });
    }
    if (!empty($region_id)) {
      $query->where(function ($query) use ($region_id) {
        $query->whereIn('viki_work_place.region_id', $region_id);
      });
    }
    if (!empty($workplaceIds)) {
      $query->where(function ($query) use ($workplaceIds) {
        $query->whereIn('viki_work_place.id', $workplaceIds);
      });
    }
    $query->groupBy('viki_worker_records.work_place_id');
    $workerRecorods = $query->paginate(35);

    //история
    activity()
      ->performedOn(Auth::user())
      ->causedBy(Auth::user())
      ->withProperties(['customProperty' => 'customValue'])
      ->log('пусната справка обекти за месец  ' . $month_id . ' за година ' . $year_id);

    return view('service::report.workerPlace', [
      'months' => $months,
      'month_id' => $month_id,
      'year_id' => $year_id,
      'years' => $years,
      'status_id' => $status_id,
      'workplaceStatuses' => $workplaceStatuses,
      'workerRecorods' => $workerRecorods
    ]);
  }

  private function getReportForWorkersByMonthYear($month_id, $year_id, $years, $months, $egn) {
    $workerRecords = array();
    $arraySum = array();
    $newSumArray = array();
    $workerRecords = $this->prepareDataForWorkers($month_id, $year_id, $egn);

    foreach ($workerRecords as $key => $records) {
      $activitiesArray = explode(",", $records->activities);
      if (!empty($workerRecorods[$key]->egn)) {
        $workerRecorods[$key]->egn = "\xEF\xBB\xBF" . $records->egn;
      }
      foreach ($activitiesArray as $activity) {

        $workplaceActivity = WorkPlaceActivity::find($activity);
		if($workplaceActivity !== null) {
			$workingHours = self::getActivityWorkingHoursForDate($workplaceActivity, $year_id . '-' . $month_id);

			$workPlaceActivityHourPrice = $workingHours != 0 ? ($workplaceActivity->neto_salary + $workplaceActivity->social_plus) / $workingHours : 0;
			//dd($workPlaceActivityHourPrice);
			$hoursByActivity = WorkerRecord::select('viki_worker_records.work_place_activity_id', DB::raw('sum(viki_worker_records.hours) as totalHours'))
				->where('worker_id', $records->worker_id)
				->where('work_place_activity_id', $workplaceActivity->id)
				->where('date', 'LIKE', $year_id . '-' . $month_id . '%')->get()->toArray();
			//print_r($hoursByActivity);
			$arraySum[$records->ID][] = $workPlaceActivityHourPrice * $hoursByActivity[0]['totalHours'];
		}	
      }
      if (!empty($arraySum)) {
        foreach ($arraySum as $key => $allSum) {
          $newSumArray[$key] = array_sum($allSum);
        }
      }
    }

    $dateCompare = $year_id . '-' . $month_id . '-01';
    $lastDayOfMonth = date("Y-m-t", strtotime($dateCompare));

    //история
    activity()
      ->performedOn(Auth::user())
      ->causedBy(Auth::user())
      ->withProperties(['customProperty' => 'customValue'])
      ->log('пусната справка работници по текущ месец  ' . $month_id . ' за година ' . $year_id);

    return view('service::report.worker', [
      'workerRecords' => $workerRecords,
      'lastDayOfMonth' => $lastDayOfMonth,
      'month_id' => $month_id,
      'year_id' => $year_id,
      'newSumArray' => $newSumArray,
      'years' => $years,
      'egn' => $egn
    ]);
  }

  public static function exportDetailedPdf(Request $request) {
    $month_id = $request->get('month_id');
    $year_id = $request->get('yearD');
    $region_id = [];
    $worker_id = [];
    
    if ((!empty($request->get('region_idD'))) && ($request->get('region_idD') != 'none')) {
      $region_id = $request->get('region_idD');
      if( strpos($region_id, ',') !== false ) {
        $region_id = explode(',', $region_id);      
      } else {
        $region_id = array($request->get('region_idD'));
      }
    }
    $workplace_id = [];
    if (!empty($request->get('workplace_idD'))) {
      $workplace_id =  $request->get('workplace_idD');
      if( strpos($workplace_id, ',') !== false ) {
        $workplace_id = explode(',',$workplace_id);      
      } else {
        $workplace_id = array($request->get('workplace_idD'));
      }
    }
    $client_id = [];
    if (!empty($request->get('client_idD'))) {
      $client_id = $request->get('client_idD');
      if( strpos($client_id, ',') !== false ) {
        $client_id = explode(',',$client_id);      
      } else {
       $client_id = array($request->get('client_idD'));
      }
    }
    $workerRecorods = array();
    $arraySum = array();
    if (($month_id == null) || ($month_id == 'novalue')) {

      return redirect()->route('service.reports')->withErrors(['Избери месеца!']);
    }

    $workerRecorods = self::prepareDataToPrint($month_id, $year_id, $workplace_id, $region_id, $client_id, $worker_id);

    foreach ($workerRecorods as $records) {
      $activitiesArray = explode(",", $records->activities);

      foreach ($activitiesArray as $activity) {

        $workplaceActivity = WorkPlaceActivity::find($activity);
        //dd($workplaceActivity);
		if($workplaceActivity !== null) {
			$workingHours = self::getActivityWorkingHoursForDate($workplaceActivity, $year_id . '-' . $month_id);

			$workPlaceActivityHourPrice = $workingHours != 0 ? ($workplaceActivity->neto_salary + $workplaceActivity->social_plus) / $workingHours : 0;
			$hoursByActivity = WorkerRecord::select('viki_worker_records.work_place_activity_id', DB::raw('sum(viki_worker_records.hours) as totalHours'))->where('worker_id', $records->worker_id)
				->where('work_place_activity_id', $workplaceActivity->id)
				->where('date', 'LIKE', $year_id . '-' . $month_id . '%')->get()->toArray();
			$arraySum[$records->ID][] = $workPlaceActivityHourPrice * $hoursByActivity[0]['totalHours'];
		}	
      }
      if (!empty($arraySum)) {
        foreach ($arraySum as $key => $allSum) {
          $newSumArray[$key] = array_sum($allSum);
        }
      }
    }

    $pdf = PDF::loadView('service::report.export', [
        'workerRecorods' => $workerRecorods,
        'arraySum' => $newSumArray,
		  'month_id' => $month_id,
	  'year_id' => $year_id
        ], ['format' => 'A4']);

    return $pdf->download('справка_за_месец_' . $month_id . '-' . $year_id . '.pdf');
  }

  public static function exportDetailedExcel(Request $request) {
    $month_id = $request->get('month_id');
    $year_id = $request->get('yearD');
    $region_id = [];
    $worker_id = [];
    
    if ((!empty($request->get('region_idD'))) && ($request->get('region_idD') != 'none')) {
      $region_id = $request->get('region_idD');
      if( strpos($region_id, ',') !== false ) {
        $region_id = explode(',', $region_id);      
      } else {
        $region_id = array($request->get('region_idD'));
      }
    }
    $workplace_id = [];
    if (!empty($request->get('workplace_idD'))) {
      $workplace_id =  $request->get('workplace_idD');
      if( strpos($workplace_id, ',') !== false ) {
        $workplace_id = explode(',',$workplace_id);      
      } else {
        $workplace_id = array($request->get('workplace_idD'));
      }
    }
    $client_id = [];
    if (!empty($request->get('client_idD'))) {
      $client_id = $request->get('client_idD');
      if( strpos($client_id, ',') !== false ) {
        $client_id = explode(',',$client_id);      
      } else {
       $client_id = array($request->get('client_idD'));
      }
    }
    $workerRecorods = array();
    $arraySum = array();
    if (($month_id == null) || ($month_id == 'novalue')) {

      return redirect()->route('service.reports')->withErrors(['Избери месеца!']);
    }
    $workerRecorods = self::prepareDataToPrint($month_id, $year_id, $workplace_id, $region_id, $client_id, $worker_id);

    foreach ($workerRecorods as $key => $records) {
      $activitiesArray = explode(",", $records->activities);
      if (!empty($workerRecorods[$key]->egn)) {
        $workerRecorods[$key]->egn = "\xEF\xBB\xBF" . $records->egn;
      }
      foreach ($activitiesArray as $activity) {

        $workplaceActivity = WorkPlaceActivity::find($activity);
        if($workplaceActivity !== null) {
			$workingHours = self::getActivityWorkingHoursForDate($workplaceActivity, $year_id . '-' . $month_id);

			$workPlaceActivityHourPrice = $workingHours != 0 ? ($workplaceActivity->neto_salary + $workplaceActivity->social_plus) / $workingHours : 0;
			$hoursByActivity = WorkerRecord::select('viki_worker_records.work_place_activity_id', DB::raw('sum(viki_worker_records.hours) as totalHours'))->where('worker_id', $records->worker_id)
				->where('work_place_activity_id', $workplaceActivity->id)
				->where('date', 'LIKE', $year_id . '-' . $month_id . '%')->get()->toArray();
			$arraySum[$records->ID][] = $workPlaceActivityHourPrice * $hoursByActivity[0]['totalHours'];
		}	
      }
      if (!empty($arraySum)) {
        foreach ($arraySum as $key => $allSum) {
          $newSumArray[$key] = array_sum($allSum);
        }
      }
    }
    $content = view('service::report.export', [
      'workerRecorods' => $workerRecorods,
      'arraySum' => $newSumArray,
	   'month_id' => $month_id,
	  'year_id' => $year_id
      ])
      ->render();
    return response($content)
        ->header("Content-Disposition", "attachment; filename=справка_за_месец_$month_id-$year_id.xls")
        ->header("Content-Type", "application/vnd.ms-excel");
  }

  private static function prepareDataToPrint($month_id, $year_id, $workplace_id, $region_id, $client_id, $worker_id) {
    $workerRecorods = array();
    $arraySum = array();

    if (($month_id == null) || ($month_id == 'novalue')) {

      return redirect()->route('service.reports')->withErrors(['Избери месеца!']);
    }
    
    $query = DB::table('viki_worker_records')
        ->select('viki_worker_records.worker_id', 'viki_workers.name', 'viki_worker_records.id as ID', 'viki_workers.family_name', 'viki_workers.middle_name', 'viki_workers.egn', 'viki_worker_records.work_place_id', 'viki_worker_records.work_place_activity_id', 'viki_work_place.name as workPlaceName', 'viki_work_place.client_id as clId', 'viki_work_place.region_id as regId', 'viki_work_place_activity.neto_salary as neto_salary', 'viki_work_place_activity.social_plus as social_plus', 'viki_work_place_activity.id as actId', 'viki_work_place_activity.activity as activity', 'viki_work_place_activity.date as selectedDate', DB::raw('sum(viki_worker_records.hours) as total'), DB::raw('group_concat( DISTINCT viki_worker_records.work_place_activity_id) as activities'))
        ->leftJoin('viki_workers', function($join) {
          $join->on('viki_workers.id', '=', 'viki_worker_records.worker_id');
        })
        ->leftJoin('viki_work_place_activity', function($join) {
          $join->on('viki_work_place_activity.id', '=', 'viki_worker_records.work_place_activity_id');
        })
        ->leftJoin('viki_work_place', function($join) {
          $join->on('viki_work_place.id', '=', 'viki_worker_records.work_place_id');
        })->where('viki_worker_records.date', 'like', $year_id . '-' . $month_id . '%');


    if (!empty($workplace_id) && ($workplace_id != 'novalue')) {
      
      $query->where(function ($query) use ($workplace_id) {
       $query->whereIn('viki_worker_records.work_place_id',$workplace_id);
      });
    }
    if (!empty($region_id) && ($region_id != 'novalue')) {
      
       $query->where(function ($query) use ($region_id) {
            $query->whereIn('viki_work_place.region_id', $region_id);
       });
    }

    if (!empty($client_id) && ($client_id != 'novalue')) {
      
      $query->where(function ($query) use ($client_id) {
        $query->whereIn('viki_work_place.client_id', $client_id);
      });
    }

    if (!empty($worker_id) && ($worker_id != 'novalue')) {
      
      $query->where(function ($query) use ($worker_id) {
        $query->whereIn('viki_worker_records.worker_id', $worker_id);
      });
    }

    $query->groupBy('viki_worker_records.worker_id', 'viki_worker_records.work_place_id');

    $workerRecorods = $query->get();
    return $workerRecorods;
  }

  private static function prepareDataForWorkers($month_id, $year_id, $egn) {
    $workerRecorods = array();
    $arraySum = array();
    $region_id = 'novalue';
    if ((Auth::user()->hasRole('manager')) || (Auth::user()->hasRole('supervisor'))) {

      $manRegion_id = VikiUser::getCurrentUserRegionId(Auth::user()->id);
      $region_id[] = $manRegion_id;
    }
    //dd($year_id);

    $query = DB::table('viki_worker_records')
        ->select('viki_worker_records.worker_id', 'viki_workers.name', 'viki_worker_records.id as ID', 'viki_workers.family_name', 'viki_workers.middle_name', 'viki_workers.egn', 'viki_worker_records.work_place_id', 'viki_worker_records.work_place_activity_id', 'viki_work_place.name as workPlaceName', 'viki_work_place.client_id as clId', 'viki_work_place.region_id as regId', 'viki_work_place_activity.neto_salary as neto_salary', 'viki_work_place_activity.social_plus as social_plus', 'viki_work_place_activity.id as actId', 'viki_work_place_activity.activity as activity', 'viki_work_place_activity.date as selectedDate', DB::raw('sum(viki_worker_records.hours) as total'), DB::raw('group_concat( DISTINCT viki_worker_records.work_place_activity_id) as activities'), DB::raw('group_concat( DISTINCT viki_worker_records.work_place_id) as placeses'))
        ->leftJoin('viki_workers', function($join) {
          $join->on('viki_workers.id', '=', 'viki_worker_records.worker_id');
        })
        ->leftJoin('viki_work_place_activity', function($join) {
          $join->on('viki_work_place_activity.id', '=', 'viki_worker_records.work_place_activity_id');
        })
        ->leftJoin('viki_work_place', function($join) {
          $join->on('viki_work_place.id', '=', 'viki_worker_records.work_place_id');
        })->where('viki_worker_records.date', 'like', $year_id . '-' . $month_id . '%');



    if (($region_id != null) && ($region_id != 'novalue')) {
      $query->where(function ($query) use ($region_id) {
        $query->where('viki_work_place.region_id', '=', $region_id);
      });
    }
    if (($egn != null) && ($egn != 'novalue')) {
      $query->where(function ($query) use ($egn) {
        $query->where('viki_workers.egn', '=', $egn);
      });
    }
    $query->groupBy('viki_worker_records.worker_id');

    $workerRecorods = $query->get();
    //dd($workerRecorods);
    return $workerRecorods;
  }

  public static function exportWorkerExcel($month_id, $year_id, $egn = null) {
    $workerRecorods = array();
    $arraySum = array();
    $newSumArray = array();
    if (($month_id == null) || ($month_id == 'novalue')) {

      return redirect()->route('service.reports')->withErrors(['Избери месеца!']);
    }

    $dateCompare = date('y') . '-' . date('m') . '-01';
    $lastDayOfMonth = date("Y-m-t", strtotime($dateCompare));

    $workerRecorods = self::prepareDataForWorkers($month_id, $year_id, $egn);

    foreach ($workerRecorods as $key => $records) {
      $activitiesArray = explode(",", $records->activities);
      if (!empty($workerRecorods[$key]->egn)) {
        //if (preg_match("/^(?:0|00)\d+$/", $records->egn)) {
        $workerRecorods[$key]->egn = "\xEF\xBB\xBF" . ($records->egn);
        //}
      }
      foreach ($activitiesArray as $activity) {

        $workplaceActivity = WorkPlaceActivity::find($activity);
        if($workplaceActivity !== null) {
			$workingHours = self::getActivityWorkingHoursForDate($workplaceActivity, $year_id . '-' . $month_id);

			$workPlaceActivityHourPrice = $workingHours != 0 ? ($workplaceActivity->neto_salary + $workplaceActivity->social_plus) / $workingHours : 0;
			$hoursByActivity = WorkerRecord::select('viki_worker_records.work_place_activity_id', DB::raw('sum(viki_worker_records.hours) as totalHours'))->where('worker_id', $records->worker_id)
				->where('work_place_activity_id', $workplaceActivity->id)
				->where('date', 'LIKE', $year_id . '-' . $month_id . '%')->get()->toArray();
			$arraySum[$records->ID][] = $workPlaceActivityHourPrice * $hoursByActivity[0]['totalHours'];
		}
      }
      if (!empty($arraySum)) {
        foreach ($arraySum as $key => $allSum) {
          $newSumArray[$key] = array_sum($allSum);
        }
      }
    }

    $content = view('service::report.exportWorker', [
      'workerRecorods' => $workerRecorods,
      'lastDayOfMonth' => $lastDayOfMonth,
      'month_id' => date('m'),
      'year_id' => date('y'),
      'newSumArray' => $newSumArray,
      'egn' => $egn
      ])->render();

    return response($content)
        ->header("Content-Disposition", "attachment; filename=справка_работници_за_месец_$month_id-$year_id.xls")
        ->header("Content-Type", "application/vnd.ms-excel");
  }

  public static function getActivityWorkingHoursForDate($workPlaceActivity, $date) {
    $workPlaceActivityHours = $workPlaceActivity
      ->hours()
      ->where(
        'date', '=', $date . '-01')
      ->first();

    if ($workPlaceActivityHours) {

      return $workPlaceActivityHours->hours_for_person;
    }
    else if ($workPlaceActivity->type_working == WorkPlaceActivity::WORKING_STANDART) {
      $date = $date . '-01';
      list($year, $month) = explode("-", $date);
      return (cal_days_in_month(CAL_GREGORIAN, $month, $year) - count(self::getAllNonWorkingDays($month, $year))) * 8;
    }

    return 0;
  }

  public static function getAllNonWorkingDays($month, $year) {
    $specialDays = self::getSpecialDays($month, $year);
    $weekDays = self::getWeekDays($month, $year);


    if ($specialDays) {
      foreach ($specialDays as $specialDay) {
        if (!in_array($specialDay, $weekDays)) {
          $weekDays[] = $specialDay;
        }
      }
    }

    return $weekDays;
  }

  public static function getSpecialDays($month, $year) {
    $specialDays = SpecialDay::where('date', 'like', date_format(date_create_from_format('d-m-Y', '01-' . $month . '-' . $year), 'Y-m-') . '%')->get();

    $specialDaysArr = [];

    foreach ($specialDays as $specialDay) {
      $specialDaysArr[] = (int) substr($specialDay->date, strrpos($specialDay->date, '-') + 1);
    }

    return $specialDaysArr;
  }

  public static function getWeekDays($month, $year) {

    $weekDays = [];

    foreach (range(1, cal_days_in_month(CAL_GREGORIAN, $month, $year)) as $day) {

      if (date('N', strtotime($day . '-' . $month . '-' . $year)) >= 6) {
        $weekDays[] = $day;
      }
    }

    return $weekDays;
  }

}