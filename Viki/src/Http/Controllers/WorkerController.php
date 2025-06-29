<?php
namespace viki\Service\Http\Controllers;

use \Illuminate\Routing\Controller;
use viki\Service\Models\Elequent\Worker;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\WorkerBonus;
use viki\Service\Models\Elequent\Region;
use viki\Service\Models\Elequent\VikiUser;
use viki\Service\Models\Elequent\WorkPlaceActivity;
use viki\Service\Models\Elequent\SpecialDay;
use viki\Service\Models\Elequent\Approvement;
use viki\Service\Request\WorkerRequest;
use viki\Service\Request\VacationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use viki\Service\Models\Elequent\Vacation;
use Illuminate\Support\Facades\Mail;
use viki\Service\Mail\VikiRequestAction;
use Carbon\Carbon;


class WorkerController extends Controller
{
	public function index(Request $request)
	{
		$keyword = ($request->get('search'));
		$keywordFirstLower = $this->mb_ucfirst(mb_strtolower($keyword),'UTF-8');
		$keywordUpperCase = mb_strtoupper($keyword);
		$keywordLowerCase = mb_strtolower($keyword);
		$status = 1;
		if(!empty($request->get('status'))) {
			$status = ($request->get('status'));
		}
		
		$perPage = 15;
		$addToQuery = '';
		
		$query = Worker::where('id','!=', 0); 
		$query = Worker::where('id','!=', 0);
		if ($status == 1){
			$query->where('unactive_from_date', NULL);
		} else {
			$query->where('status','=',1);
		}
		if ((Auth::user()->hasRole('manager')) 
			|| (Auth::user()->hasRole('supervisor')))
		{
			$managerRegion = VikiUser::getCurrentUserRegionId(Auth::user()->id);
			$query->whereIn('region_id' , $managerRegion);
			
		}
		
		if (!empty($keyword)) {
			$query->where(function ($queryw) use ($keyword, $keywordFirstLower, $keywordUpperCase, $keywordLowerCase) {
								$queryw->where('name', 'LIKE', "%$keyword%")
									->orWhere('middle_name', 'LIKE', "%$keyword%")
									->orWhere('family_name', 'LIKE', "%$keyword%")
									->orWhere('name', 'LIKE', "%$keywordFirstLower%")
									->orWhere('middle_name', 'LIKE', "%$keywordFirstLower%")
									->orWhere('family_name', 'LIKE', "%$keywordFirstLower%")
									->orWhere('name', 'LIKE', "%$keywordUpperCase%")
									->orWhere('middle_name', 'LIKE', "%$keywordUpperCase%")
									->orWhere('family_name', 'LIKE', "%$keywordUpperCase%")
									->orWhere('name', 'LIKE', "%$keywordLowerCase%")
									->orWhere('middle_name', 'LIKE', "%$keywordLowerCase%")
									->orWhere('family_name', 'LIKE', "%$keywordLowerCase%")
									->orWhere('neto_salary', 'LIKE', "%$keyword%")
									->orWhere('egn', 'LIKE', "%$keyword%");
								})->where('id', '!=', auth()->id());
		}
		$query->orderBy('name', 'ASC');
		$query->orderBy('family_name', 'ASC');
		$workers = $query->paginate($perPage);
		return view('service::worker.index',[
			'workers' => $workers,
			'status' => $status
		]);
	}
	
	private function mb_ucfirst($string, $encoding)
	{
		$strlen = mb_strlen($string, $encoding);
		$firstChar = mb_substr($string, 0, 1, $encoding);
		$then = mb_substr($string, 1, $strlen - 1, $encoding);
		return mb_strtoupper($firstChar, $encoding) . $then;
	}
	
	public function createWorker(WorkerRequest $request)
	{
		$all = $request->all();
		if ($request->region_id == 'none') {
			return Redirect::back()->withErrors(['Избери регион!', 'Избери регион!']);   
		}
		if ($request->has('region')) {
				$reg = Region::find($request->region);
				if(empty($reg)) {
					return Redirect::back()->withErrors(['Няма такъв регион!']);
				}
		}
		if (!$request->has('work_place_id')) {
				return Redirect::back()->withErrors(['Избери обект!']);
		}
		if ($request->work_place_id == 'none') {
			unset($all['work_place_id']);
			return Redirect::back()->withErrors(['Избери обект!']);
		} else {
			$wpi = WorkPlace::where('id',$request->work_place_id)->where('region_id',$request->region)->get();
				if(empty($wpi)) {
					return Redirect::back()->withErrors(['Няма такава дейност!']);
				}
		}
		if (($request->work_place_activity_id == 'none')
			|| (!$request->has('work_place_activity_id')))
		{
			return Redirect::back()->withErrors(['Избери дейност!'])->withInput();
		}
		$worker = Worker::create($all);
		//история
		activity()
			->performedOn($worker)
			->causedBy(Auth::user())
			->withProperties(['customProperty' => 'customValue'])
			->log('създаден служител: '.$worker->name.' '.$worker->middle_name.' '.$worker->family_name);
		
		return redirect()->route('service.worker')->with('success', 'Успешно създадохте служител!');

	}
	
	public function insertHolidays()
	{
		$holidaysResult = SpecialDay::insertHolidays();
		$year = date('Y');
		$holidays = SpecialDay::where('date', 'LIKE',$year."-%")
                                    ->where('type',"=",1)
                                    ->get();
		return view('service::worker.holidays',
			[
				'holidaysResult' => $holidaysResult,
				'holidays' => $holidays
				
		]);
		
	}
	
	public function viewFormWorker()
	{
		$workplaces = WorkPlace::where('status','=', WorkPlace::WORK_PLACE_ACTIVE)->orderBy('name', 'asc')->get();
		$statuses = Worker::workerStatuses();
		$typesWork = Worker::workerTypeWorking();
		$regions = Region::where('status','=', Region::REGION_ACTIVE)->orderBy('name', 'asc')->get();
		$workplaceactivities = WorkPlaceActivity::where('date', '=', null)->orderBy('activity', 'asc')->get();
		
		return view('service::worker.create',
			[
				'workplaces' => $workplaces,
				'statuses' => $statuses,
				'typesWork' => $typesWork,
				'regions'    => $regions,
				'workplaceactivities' => $workplaceactivities
				
		]);

	}
	
	public function createVacation(VacationRequest $request, $id)
	{   
		$all = $request->all();
		$all['worker_id'] = $id;
		$validate = false;
		$validate = $this->validateLeave($all['start_date'], $all['end_date'], $id);
		
		if($validate == true) {
			$vacation = Vacation::create($all);
			//история
			activity()
				->performedOn($vacation)
				->causedBy(Auth::user())
				->withProperties(['customProperty' => 'customValue'])
				->log('създадена ваканция с коментар: '.$vacation->comment.' за потребителя '.$vacation->worker->name.' '.$vacation->worker->family_name);
			
			return Redirect::back();
		} else {
			
			return Redirect::back()->withErrors(['Има вече ваканция за този период или застъпващи периоди!', 'Има вече ваканция за този период!']); 
		}

	}
	public function viewFormVacation($workerId){
		
		$worker = Worker::find($workerId);
		$statuses = Vacation::vacationStatuses();
		$vacations = Vacation::where('worker_id','=',$workerId)->paginate(10);
		
		return view('service::worker.vacation',
			[
				'worker' => $worker,
				'statuses' => $statuses,
				'vacations' => $vacations
		]);
	}
	
	
	public function validateLeave($start_date, $end_date, $id,$vac_type = null)
	{
		$start_date = Carbon::parse($start_date)->format('Y-m-d');
		$end_date = Carbon::parse($end_date)->format('Y-m-d');
		
		if ($end_date < $start_date) {
			return false;
		}
        $oldVacations = Vacation::where(function ($query) use ($start_date, $end_date, $id) {
                $query->where('start_date', '<=', $start_date)
                    ->where('end_date', '>=', $end_date)
                    ->where('worker_id', $id);
            })->orWhere(function ($query) use ($start_date, $end_date, $id) {
                $query->where('start_date', '>=', $start_date)
                    ->where('end_date', '<=', $end_date)
                    ->where('worker_id', $id);
            })->orWhere(function ($query) use ($start_date,$end_date, $id) {
                    $query->orWhereBetween('end_date', [$start_date, $end_date])
                    ->where('worker_id', $id);
            })->orWhere(function ($query) use ($start_date,$end_date, $id) {
                    $query->orWhereBetween('start_date', [$start_date, $end_date])
                    ->where('worker_id', $id);
            })->orWhere(function ($query) use ($start_date,$id) {
                $query->where('end_date', '=', $start_date)
                    ->where('worker_id', $id);
            })->orWhere(function ($query) use ($end_date, $id) {
                $query->where('start_date', '=', $end_date)
                    ->where('worker_id', $id);
            })
            ->count();
           

        if ( $oldVacations > 0) {
			
			return false;
            
        }

        return true;
    }
	/**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     *
     * @return void
     */
	public function edit($id)
	{
		$worker = Worker::findOrFail($id);
		$workplaces = WorkPlace::all();
		$workplaceactivity = array();
		if (!empty($worker->work_place_id)) {
			$workplaceactivity = WorkPlaceActivity::where('id','=',$worker->work_place_activity_id)->first();
		}
		$workplaceactivities = WorkPlaceActivity::where('date', '=', null)->get();
		$statuses = Worker::workerStatuses();
		$typesWork = Worker::workerTypeWorking();
		$regions = Region::where('status','=', Region::REGION_ACTIVE)->get();
		
		return view('service::worker.edit',
			[
				'worker' => $worker,
				'regions'    => $regions,
				'workplaces' => $workplaces,
				'statuses' => $statuses,
				'typesWork' => $typesWork,
				'workplaceactivities' => $workplaceactivities,
				'workplaceactivity' => $workplaceactivity
				
		]);
       
    }
	
	/**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int $id
     *
     * @return void
     */
    public function update(Request $request, $id)
    {
		try{
			$worker = Worker::findOrFail($id);
			$all = $request->all();
			
			if ($request->region_id == 'none') {
				return Redirect::back()->withErrors(['Избери регион!', 'Избери регион!'])->withInput();   
			}
			if ($request->has('region')) {
				$reg = Region::find($request->region);
				if(empty($reg)) {
					return Redirect::back()->withErrors(['Няма такъв регион!']);
				}
			}
			if ($request->work_place_activity_id == null) {
				$request->work_place_activity_id  = $worker->work_place_activity_id;
				//return Redirect::back()->withErrors(['Избери дейност!'])->withInput(); 
			}
			if (!$request->has('work_place_id')) {
				return Redirect::back()->withErrors(['Избери обект!'])->withInput();
			}
			if (isset($request['unactive_from_date'])) {
					$all['unactive_from_date'] = Carbon::parse($all['unactive_from_date'])->format('Y-m-d');
			}
			
			if (($request->has('status')) && ($request->status == 0)) {
				$all['unactive_from_date'] = NULL;
			}
			if ((isset($request['note'])) 
				&& ($request['note'] == null)){
					$all['note'] = '';
			}
			$worker->update($all);
			//история
			activity()
				->performedOn($worker)
				->causedBy(Auth::user())
				->withProperties(['customProperty' => 'customValue'])
				->log('редактиран служител: '.$worker->name.' '.$worker->middle_name.' '.$worker->family_name);
			$workers= Worker::paginate(15);
			
			return redirect('service/worker')->with('flash_message', 'Работникът е редактиран успешно!');
			
		}  catch ( \Illuminate\Database\QueryException $e) {

			return Redirect::back()->withErrors(['Грешка']);
		}
    }

     /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     *
     * @return void
     */
    public function destroy($id)
    {
       $worker = Worker::destroy($id);
		//история
		activity()
			->performedOn($worker)
			->causedBy(Auth::user())
			->withProperties(['customProperty' => 'customValue'])
			->log('деактивиран служител: '.$worker->name.' '.$worker->middle_name.' '.$worker->family_name);
		
        return redirect('service/worker')->with('flash_message', 'Работникът е изтрит');
    }
	
	/**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     *
     * @return void
     */
    public function destroyVacation($id)
    {	
		$vacation = Vacation::find($id);
		//история
		activity()
			->performedOn($vacation)
			->causedBy(Auth::user())
			->withProperties(['customProperty' => 'customValue'])
			->log('ваканцията беше изтрита: '.$vacation->comment.' за потребителя '.$vacation->worker->name.' '.$vacation->worker->family_name);
		
		Vacation::destroy($id);
				
        return Redirect::back()->with(['Ваканцията беше изтрита!']);
    }
	public function bonus($id) {
    $worker = Worker::findOrFail($id);

    $allWorkplaces = [
      $worker->workplace
    ];

    foreach ($worker->temporaryWorkplace as $temporaryWorkplace) {
      if ($worker->workplace['id'] !== $temporaryWorkplace['id']) {
        $allWorkplaces[] = $temporaryWorkplace;
      }
    }
    
    $allWorkplaces = (array_unique(array_column($allWorkplaces, 'name','id')));
    return view('service::worker.bonus', [
      'worker' => $worker,
      'workplaces' => $allWorkplaces,
      'bonusTypes' => [
        WorkerBonus::$typeBonus = "Бонус",
        WorkerBonus::$typePayCut = "Наказание",
      ]
      ]
    );
  }

  public function saveBonus(Request $request, $id) {
    //check if approvemnt is going to be created
    $bonusDate = \DateTime::createFromFormat('d-m-Y', '01-' . $request->get('bonusDate'));
    $dateOld = new \DateTime("-2 months");
    if ($bonusDate->getTimestamp() >  $dateOld->getTimestamp()){
       $bonusPunishment = WorkerBonus::create([
        'sum' => $request->get('bonusValue'),
        'type' => $request->get('type'),
        'for_month' => $bonusDate,
        'work_place_id' => $request->get('workplaceId'),
        'worker_id' => $request->get('workerId')
      ]);
      if (($request->get('type') == WorkerBonus::BONUS) && ($bonusPunishment)) {
        $workPlace = Workplace::find($request->get('workplaceId'));
        $workPlaceBudget = $workPlace->getBudgetByDate($request->get('bonusDate'));
        $dates = explode('-', $request->get('bonusDate'));
        $findAllWorkPlaceActivities = WorkPlaceActivity::where('work_place_id', '=', $request->get('workplaceId'))
          ->where('date', 'like', '%' . $dates[1] . "-" . $dates[0] . "-" . '%')
          ->get();
        $sum = 0;
        $overBudget = $workPlace->overBudget()
          ->where('date', 'like', '%' . $dates[1] . "-" . $dates[0] . "-" . '%')->first();

        if ($overBudget) {
            $workPlaceBudget = $workPlaceBudget + $overBudget->sum_up;
        }

        foreach ($findAllWorkPlaceActivities as $addedActivity) {
          $sum = $sum + (($addedActivity['neto_salary'] + $addedActivity['social_plus']) * $addedActivity['worker_count']);
        }
        if (($sum + $request->get('bonusValue')) > $workPlaceBudget) {
          $approvalId = $this->createApproveRequest($request->get('workplaceId'), $request->get('bonusDate'), $request->get('bonusValue'), $bonusPunishment->id);
          if($overBudget) {
           return redirect()->route('service.worker.bonus', $id)->with('success', 'Успешно добавен Бонус/Наказание. Добавено одобрение. Над бюджета '.$overBudget->sum_up.'+ бюджета за месеца '.$workPlaceBudget);
          }
           return redirect()->route('service.worker.bonus', $id)->with('success', 'Успешно добавен Бонус/Наказание. Добавено одобрение');
        }
      }

     
      return redirect()->route('service.worker.bonus', $id)->with('success', 'Успешно добавен "Бонус/Наказание"');
    }
    return Redirect::back()->withErrors(['Не може да се въвежда за стар или приключен месец!']);
  }

  public function deleteBonus($id) {
    $workerBonus = WorkerBonus::findOrFail($id);
    $bonusDate = $workerBonus->for_month;
    $dates = explode('-', $bonusDate);
    $month = date('m');
    
    if (trim($dates[1]) >= trim($month)) {
      $workerId = $workerBonus->worker_id;

      $workerBonus->delete();

      return redirect()->route('service.worker.bonus', $workerId)->with('success', 'Успешно премахнат "Бонус/Наказание"');
    }
     return Redirect::back()->withErrors(['Не може да триеш за стар месец!']);
  }
   private function createApproveRequest($workPlaceId, $date, $overBudget, $workerBonusId) {
    $approveRequest = new Approvement();
    $approveRequest->work_place_id = $workPlaceId;
    $approveRequest->date = date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-d');
    $approveRequest->creator_id = Auth::user()->id;
    $approveRequest->status = Approvement::STATUS_NEW;
    $approveRequest->type_id = Approvement::TYPE_APPR_BONUS;
    $approveRequest->sum_above_budget = $overBudget;
    $approveRequest->viki_worker_bonus_id = $workerBonusId;

    $approveRequest->save();
    $workPlace = WorkPlace::find($workPlaceId);
    $regions = $workPlace->region()->get();

    foreach ($regions as $region) {

      $managers = $region->managers()->get();

      foreach ($managers as $manager) {
        if (!empty($manager->email)) {
          $mail = Mail::to($manager->email);

          $mail->send(new VikiRequestAction([
            'reason' => 'повишаване на бюджета - добавен бонус на работник',
            'workerplace' => $workPlace->name,
           'userWhoTriggerChange' => Auth::user()->name,
            'link' => route('service.approvement')
          ]));
        }
      }
    }
    return $approveRequest->id;
  }
}