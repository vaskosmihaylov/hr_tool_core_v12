<?php

namespace viki\Service\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;
use viki\Service\Mail\VikiRequestAction;
use viki\Service\Models\Elequent\Approvement;
use viki\Service\Models\Elequent\Archive;
use viki\Service\Models\Elequent\VikiUser;
use viki\Service\Models\Elequent\Worker;
use viki\Service\Models\Elequent\WorkerRecord;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\WorkPlaceActivity;
use viki\Service\Models\Elequent\HoursActivityByMonth;
use viki\Service\Models\Elequent\WorkPlaceActivityHoursPerDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use viki\Service\Request\WorkPlaceActivityByMonthRequest;
use Spatie\LaravelPdf\Facades\Pdf as PDF;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Mail;
use DatePeriod;
use DateTime;
use DateInterval;
use viki\Service\Traits\ApprovalTrait;
use viki\Service\Traits\PresenceTableTrait;

class PresenceController extends Controller
{
    use AuthorizesRequests, ValidatesRequests, ApprovalTrait, PresenceTableTrait;

    public function index($workPlaceId = null, $date = null)
    {
        $availableMonths = [
            date('m-Y', strtotime(date('Y-m')." -1 month")),
            date('m-Y', strtotime(date('Y-m'))),
            date('m-Y', strtotime(date('Y-m')." +1 month")),
        ];

        $availableMonths = array_combine($availableMonths, $availableMonths);

        if ($date) {

            if (in_array($date, $availableMonths)) {

                $arr = explode("-", $date, 2);
                $month = $arr[0];
                $year = $arr[1];
            } else {
                abort(404);
            }

        } else {
            $month = date('m');
            $year = date('Y');
        }

        $selectedDate = $month . '-'. $year;

        $weekDays = $this->getAllNonWorkingDays($month, $year);

        $monthDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $user = VikiUser::find(Auth::user()->id);

        if (Auth::user()->hasRole('manager')) {
           // $userWorkPlaces = $user->regions()->first()->activeWorkPlaces()->orderBy('name')->get();
		     $region_id = VikiUser::getCurrentUserRegionId(Auth::user()->id);
             $userWorkPlaces = WorkPlace::where('status', WorkPlace::WORK_PLACE_ACTIVE)
                              ->whereIn('viki_work_place.region_id', $region_id)->orderBy('name')->get();

        } else if (Auth::user()->hasRole('supervisor')) {
            $userWorkPlaces = $user->activeWorkPlaces()->orderBy('name')->get();

        } else if (Auth::user()->hasRole('admin')) {
            $userWorkPlaces = WorkPlace::where('status', WorkPlace::WORK_PLACE_ACTIVE)->orderBy('name')->get();
        }

        if ($workPlaceId) {
            $selectedWorkPlace = WorkPlace::findOrFail($workPlaceId);
        } else if ($userWorkPlaces->count()) {

            $selectedWorkPlace = $userWorkPlaces->first();
            $workPlaceId = $selectedWorkPlace->id;
        }

        $userWorkPlaces = $userWorkPlaces->pluck('name', 'id');

        if ($workPlaceId) {

            $tableNotEditable = 'false';

            $selectedWorkPlaceActivities = WorkPlaceActivity::where('work_place_id','=',$selectedWorkPlace->id)
                ->where('date' , '!=', null)
                ->where(function($q) use ($selectedDate) {

                    $date = date_format(date_create_from_format('d-m-Y', '01' . '-' . $selectedDate), 'Y-m-d');

                    $q->where('date', $date)
                        ->orWhere('date', null);
                })
                ->get();

            if ($selectedWorkPlaceActivities->count() > 0) {

                $tableData = $this->prepareTableData($selectedWorkPlaceActivities, $selectedDate, $selectedWorkPlace);

                $waitingApprovalsCount = Approvement::where('status', Approvement::STATUS_NEW)
                    ->where('work_place_id', $workPlaceId)
                    ->where('date', 'like', date_format(date_create_from_format('d-m-Y', '01' . '-' . $selectedDate), 'Y-m-') . '%')
                    ->count();

                if ($waitingApprovalsCount > 0) {
                    $tableNotEditable = 'true';
                }
            } else {

                $tableData = null;
                $tableNotEditable = 'true';
            }
			$checkIfAlreadyCopied = WorkPlaceActivity::checkIfActivitiesAreCopied($workPlaceId, $year, $month);

            $tableIsFinished = false;

            if ($tableData) {

                foreach ($tableData as $tableDatum) {
                    foreach ($tableDatum['workPlaceActivityWorkers'] as $workPlaceActivityWorker) {
                        foreach ($workPlaceActivityWorker->workerRecords as $workerRecord) {
                            if ((int)$workerRecord->status === WorkerRecord::WORKER_RECORD_FINISHED) {
                                $tableIsFinished = true;
                            }
                        }
                    }
                }
            }

            if ($tableIsFinished) {
                $tableNotEditable = 'true';
            }

            return view('service::presence.index', compact('weekDays','checkIfAlreadyCopied', 'monthDays', 'tableData','selectedWorkPlace', 'userWorkPlaces', 'workPlaceId', 'availableMonths', 'selectedDate', 'tableNotEditable', 'tableIsFinished'));
        } else {
            $tableData = null;
            $selectedWorkPlace = null;
            $tableNotEditable = 'true';
            $tableIsFinished = false;

			$checkIfAlreadyCopied = WorkPlaceActivity::checkIfActivitiesAreCopied($workPlaceId, $year, $month);

            return view('service::presence.index', compact('weekDays','checkIfAlreadyCopied' , 'monthDays', 'tableData','selectedWorkPlace', 'userWorkPlaces', 'workPlaceId', 'availableMonths', 'selectedDate', 'tableNotEditable', 'tableIsFinished'));
        }
    }

    public function saveTableData(Request $request)
    {
        $requestData = json_decode($request->all()['json'], true);

        $workPlaceId = $requestData['workPlaceId'];
        $date = $requestData['date'];
        $userData = $requestData['userData'];

        $extraHours = [];

        foreach ($userData as $key =>  $userDatum) {

            if (is_numeric($userDatum['hours'])) {
                if (array_key_exists($userDatum['workPlaceActivityId'], $extraHours)) {
                    $extraHours[$userDatum['workPlaceActivityId']] += $userDatum['hours'];
                } else {
                    $extraHours[$userDatum['workPlaceActivityId']] = $userDatum['hours'];
                }
            } else {
                unset($userData[$key]);
            }
        }

        DB::beginTransaction();

        try {

            $checkOverBudget = $this->checkIfInBudget($workPlaceId, $date, $userData);

            if ($checkOverBudget['inBudget'] === true) {

                foreach ($userData as $userDatum) {
                    $this->saveWorkerRecord($userDatum, $workPlaceId, $date, WorkerRecord::WORKER_RECORD_APPROVED);
                }

                DB::commit();

            } else {

                $approvalId = $this->createApproveRequest($workPlaceId, $date, $checkOverBudget['overBudget']);

                if (count($checkOverBudget['dataNegativeValueKeys']) > 0) {

                    foreach ($checkOverBudget['dataNegativeValueKeys'] as $negativeValueKey) {
                        $this->saveWorkerRecord($userData[$negativeValueKey], $workPlaceId, $date, WorkerRecord::WORKER_RECORD_APPROVED);
                        unset($userData[$negativeValueKey]);
                    }

                    $checkOverBudget = $this->checkIfInBudget($workPlaceId, $date, $userData);
                }

                DB::commit();

                foreach ($userData as $userDatum) {

                    if (($checkOverBudget['freeBudgetBeforeChange'] - ($checkOverBudget['workPlaceActivityCostForHour'][$userDatum['workPlaceActivityId']] * $userDatum['hours'])) >= 0) {

                        $checkOverBudget['freeBudgetBeforeChange'] = $checkOverBudget['freeBudgetBeforeChange'] - ($checkOverBudget['workPlaceActivityCostForHour'][$userDatum['workPlaceActivityId']] * $userDatum['hours']);
                        $this->saveWorkerRecord($userDatum, $workPlaceId, $date, WorkerRecord::WORKER_RECORD_APPROVED);

                    } else {

                        $this->saveWorkerRecord($userDatum, $workPlaceId, $date, WorkerRecord::WORKER_RECORD_WAITING, $approvalId);
                    }
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollback();
        }

        return redirect()->route('service.presence.show.workplace.date', ['workPlaceId' => $workPlaceId, 'date' => $date]);

    }

    public function editActivity($id, $date)
    {
        $workplaceActivity = WorkPlaceActivity::find($id);
        $arr = explode("-", $date);
        $month = $arr[0];
        $year = $arr[1];
        $newDate = $arr[1]."-".$arr[0]."-01";
        $hours = HoursActivityByMonth::where('work_place_activity_id','=',$workplaceActivity->id)
            ->where('date','=',$newDate)
            ->get()->toArray();
        $hour = 0;
        if(count($hours)!= 0){
            $hour = $hours[0]['hours_for_person'];
        }

        return view('service::presence.form_workplace_activitiesbymonth_edit',
            [
                'workplaceActivity' => $workplaceActivity,
                'hour'             => $hour,
                'date'            => $newDate
            ]);

    }

    public function updateActivity(Request $request, $id , $date)
    {
        try{

            $workplaceActivity = WorkPlaceActivity::findOrFail($id);

            if(!empty($workplaceActivity->date)) {
                $workplaceActivity->update($request->all());
            }
            $arr = explode("-", $date);
            $month = $arr[1];
            $year = $arr[0];
            $newDate = $arr[0]."-".$arr[1]."-01";

            //история
            activity()
                ->performedOn($workplaceActivity)
                ->causedBy(Auth::user())
                ->withProperties(['customProperty' => 'customValue'])
                ->log('променена временна дейност: '.$workplaceActivity->activity.' за дата '.$date.'и  обект '.$workplaceActivity->workplace->name);
            //add na chasovete
            $hoursPerson = $request->all();
            $hoursPerson = $hoursPerson['hours_for_person'];
            HoursActivityByMonth::updateOrCreate(
                [
                    'work_place_activity_id' => $workplaceActivity->id,
                    'date' => $newDate,
                ],
                [
                    'hours_for_person' => $hoursPerson,
                    'created_by' =>  Auth::id()
                ]
            );

            $dates = explode('-',$date);

            return redirect('service/presence/config/'.$workplaceActivity->work_place_id.'/'.$dates[1].'-'.$dates[0])->with('flash_message', 'Редактирахте дейността!');

        }  catch ( \Illuminate\Database\QueryException $e) {

            return Redirect::back()->withErrors(['Грешка']);
        }
    }

    public function createWorkPlaceActivityByMonth(WorkPlaceActivityByMonthRequest $request, $id, $date)
    {
        try{
            //id is the activity id
            DB::beginTransaction();
            $dateArr = explode('-',$date);
            $attributes['date'] = $dateArr[1]."-".$dateArr[0]."-01";
            $date  = $attributes['date'];
            $workplaceActivity = WorkPlaceActivity::create($request->all(), $id, $date);
            $all = $request->all();

            $checkBudget = self::checkTheWorkplaceBudget($request->all(), $workplaceActivity->work_place_id, $date, $id);

            if($checkBudget == false) {
                DB::rollBack();
                return Redirect::back()->withErrors('Добавяйки тази дейност надвишавате бюджета на обекта!')->withInput();
            }

            $insertHours = HoursActivityByMonth::create($all['hours_for_person'], $workplaceActivity->id, $date);
            $dates = explode('-',$date);
            //история
            activity()
                ->performedOn($workplaceActivity)
                ->causedBy(Auth::user())
                ->withProperties(['customProperty' => 'customValue'])
                ->log('добавена нова временна дейност: '.$workplaceActivity->activity.' за дата '.$date.' и  обект '.$workplaceActivity->workplace->name);

            DB::commit();

            return redirect('service/presence/config/'.$workplaceActivity->work_place_id.'/'.$dates[1].'-'.$dates[0])->with('flash_message', 'Добавихте дейността!');

        } catch (Exception $ex) {

            DB::rollBack();
            return Redirect::back()->withErrors($ex);
        }
    }

    public function storeAddWorkerRecords(Request $request, $workPlaceId, $date)
    {
        try{
            $workplace = WorkPlace::findOrFail($workPlaceId);
            $workplaceActivity = WorkPlaceActivity::findOrFail($request->work_place_activity_id);
            $dates = explode("-", $date);
            $date = $dates[1].'-'.$dates[0].'-01';
            $worker = Worker::findOrFail($request->worker_id);

            ///attach worker to activity and place for the chossen month
            $workplace->temporaryWorkers()->save($worker, ['date'=>$date]);
            $workplaceActivity->temporaryWorkers()->save($worker, ['date'=>$date]);
			//add automatic users if worker and workplaceactivity are standart
			if (($workplaceActivity->type_working == WorkPlaceActivity::WORKING_STANDART))
			{


				$dateCompare = $dates[1].'-'.$dates[0].'-01';
				$lastDayOfMonth = date("Y-m-t", strtotime($dateCompare));
				$startDate = $worker->start_date;
				$startDateMonthYear =  date("Y", strtotime($startDate));
                $startDateMonth =  date("m", strtotime($startDate));
                $lastDayOnlyMonthYear = date("Y", strtotime($dateCompare));

				if ($startDateMonthYear < $lastDayOnlyMonthYear) {
                        $startDate = $dates[1].'-'.$dates[0].'-01';

                 }
				if (($startDateMonthYear == $lastDayOnlyMonthYear)
					&& ($startDateMonth < $dates[0])){
					$startDate = $dates[1].'-'.$dates[0].'-01';

				}
                if ($startDate <= $lastDayOfMonth) {
					$this->insertStandartWorkingPeople($worker, $startDate, $lastDayOfMonth, $workplaceActivity, $workplaceActivity);
				}
			}
            //история
            activity()
                ->performedOn($workplaceActivity)
                ->causedBy(Auth::user())
                ->withProperties(['customProperty' => 'customValue'])
                ->log('добавен работник: '.$worker->name.' '.$worker->family_name.' към присъствената форма за дата '.$date.'и за обект '.$workplace->name);

            return Redirect::back()->with('flash_message', 'Добавихте работника!');

        } catch (\Exception $e) {
            return Redirect::back()->withErrors('Този работник вече е закачен към тази дейност!');
		}
    }

    public function viewFormWorkPlaceActivityAdd($id, $date)
    {
        $workplaceActivities = WorkPlaceActivity::where('work_place_id','=',$id)->paginate(5);
        $workplace = WorkPlace::find($id);
        $typesWork = WorkPlaceActivity::workerTypeWorking();


        return view('service::presence.form_workplace_activities',
            [
                'workplaceActivities' => $workplaceActivities,
                'workplace' => $workplace,
                'typesWork' => $typesWork,
                'date' => $date

            ]);
    }

    public function viewAddWorker($workPlaceId, $date)
    {
        $today = Carbon::now();

        $dates = explode("-", $date);
        $workPlace = WorkPlace::find($workPlaceId);
        $region_id = $workPlace->region_id;
        $workplaceName = $workPlace->name;
        if(!empty($region_id)) {
            //get the workers in the region
            $dateCompare =$dates[1]."-".$dates[0]."-01";
            $lastDayOfMonth = date("Y-m-t", strtotime($dateCompare));
            $workers = Worker::where('region_id', '=',$region_id)
                ->where('status','=',WORKER::USER_ACTIVE)
                ->where('start_date','<=',$lastDayOfMonth)
				->orderBy('name')
                ->orderBy('family_name')
                ->get();

            // retrieve the month
            $workPlaceActivityByMonth = WorkPlaceActivity::where('work_place_id','=',$workPlaceId)
                ->where('date','like','%' . $dates[1]."-".$dates[0]."-" . '%')
                ->get();

            return view('service::presence.add_worker',
                [
                    'workPlaceActivityByMonth' => $workPlaceActivityByMonth,
                    'workPlaceId'              => $workPlaceId,
                    'workplaceName'            => $workplaceName,
                    'date'                     => $date,
                    'workers'                  => $workers
                ]);
        } else{
            return redirect('service/presence')->with('flash_message', 'Грешка-Няма такъв регион!');
        }

    }

    public function viewConfigForm($workPlaceId, $date)
    {
        $getHours = array();
        $arr = explode("-", $date);
        $month = $arr[0];
        $year = $arr[1];
        $newDate = $arr[1]."-".$arr[0]."-01";
        $checkIfAlreadyCopied = WorkPlaceActivity::checkIfActivitiesAreCopied($workPlaceId, $year, $month);
        $workplace = WorkPlace::find($workPlaceId);
        $workPlaceName = $workplace->name;
        if (!WorkPlaceActivity::checkIfActivitiesAreCopied($workPlaceId, $year, $month)) {
            //get the worrkplace activities which are common and insert them for the month
            $workPlaceActivityByMonthCommon = WorkPlaceActivity::where('work_place_id','=',$workPlaceId)
                ->where('date', null)
                ->get();

            foreach($workPlaceActivityByMonthCommon as $commonAct) {

                $workers = Worker::where('status', Worker::USER_ACTIVE)
                    ->where('work_place_activity_id','=', $commonAct->id)->get();

                $workplaceActivity = $this->addCopiedActivitiesFromWorkplace($commonAct, $year, $month);

                foreach($workers as $workerToAdd) {

                    $dateCompare = $year."-".$month."-01";
                    $lastDayOfMonth = date("Y-m-t", strtotime($dateCompare));
                    $startDate = $workerToAdd->start_date;
                    $startDateMonthYear =  date("Y", strtotime($startDate));
                    $startDateMonth =  date("m", strtotime($startDate));
                    $lastDayOnlyMonthYear = date("Y", strtotime($dateCompare));

                    if ($startDateMonthYear < $lastDayOnlyMonthYear) {
                        $startDate = $year."-".$month."-01";

                    }
                    if (($startDateMonthYear == $lastDayOnlyMonthYear)
                        && ($startDateMonth < $month)){
                        $startDate = $year."-".$month."-01";

                    }
                    if ($startDate <= $lastDayOfMonth) {
                        //hora zapochnali rabota veche
                        ///attach worker to activity and place for the chossen month
                        $workplace->temporaryWorkers()->save($workerToAdd, ['date'=>$year."-".$month."-01"]);
                        $workplaceActivity->temporaryWorkers()->save($workerToAdd, ['date'=>$year."-".$month."-01"]);

                        $this->insertStandartWorkingPeople($workerToAdd, $startDate, $lastDayOfMonth, $commonAct, $workplaceActivity);
                    }

                }
            }

        }
        // retrieve the month
        $workPlaceActivityByMonth = WorkPlaceActivity::where('work_place_id','=',$workPlaceId)
            ->where('date','like','%' . $year."-".$month."-" . '%')
            ->get();

        foreach($workPlaceActivityByMonth as $workPlaceActivity){

            $hours = HoursActivityByMonth::where('work_place_activity_id','=',$workPlaceActivity->id)
                ->where('date','=',$newDate)
                ->get()->toArray();

            if (count($hours)!= 0) {

                $getHours[$workPlaceActivity->id] = $hours[0]['hours_for_person'];
            }
        }

        return view('service::presence.configuration',
            [
                'workPlaceActivityByMonth' => $workPlaceActivityByMonth,
                'workPlaceId'              => $workPlaceId,
                'date'                     => $date,
                'getHours'                 => $getHours,
                'checkIfAlreadyCopied'     => $checkIfAlreadyCopied,
                'workPlaceName'           => $workPlaceName,
            ]);

    }

    public function finish(Request $request) {

        $requestData = json_decode($request->all()['json'], true);

        $workPlaceId = $requestData['workPlaceId'];

        $date = $requestData['date'];

        if ($workPlaceId) {
           $workplace = WorkPlace::findOrFail($workPlaceId);
        } else {
            abort(404);
        }

        $availableMonths = [
            date('m-Y', strtotime(date('Y-m')." -1 month")),
            date('m-Y', strtotime(date('Y-m'))),
            date('m-Y', strtotime(date('Y-m')." +1 month")),
        ];

        if ($date) {
            if (!in_array($date, $availableMonths)) {
                abort(404);
            }
        } else {
            abort(404);
        }

        WorkerRecord::where('work_place_id', $workPlaceId)
            ->where('date', 'like', date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-') . '%')
            ->update([
                'status' => WorkerRecord::WORKER_RECORD_FINISHED
            ]);
		//история
		activity()
			->performedOn($workplace)
			->causedBy(Auth::user())
			->withProperties(['customProperty' => 'customValue'])
			->log('приключен месец за дата '.$date.' и обект '.$workplace->name);

        return redirect()->route('service.presence.show.workplace.date', ['workPlaceId' => $workPlaceId, 'date' => $date]);
    }

    public function unfinish(Request $request) {
        $requestData = json_decode($request->all()['json'], true);

        $workPlaceId = $requestData['workPlaceId'];

        $date = $requestData['date'];

        if ($workPlaceId) {
            $workplace = WorkPlace::findOrFail($workPlaceId);
        } else {
            abort(404);
        }

        $availableMonths = [
            date('m-Y', strtotime(date('Y-m')." -1 month")),
            date('m-Y', strtotime(date('Y-m'))),
            date('m-Y', strtotime(date('Y-m')." +1 month")),
        ];

        if ($date) {
            if (!in_array($date, $availableMonths)) {
                abort(404);
            }
        } else {
            abort(404);
        }

        WorkerRecord::where('work_place_id', $workPlaceId)
            ->where('date', 'like', date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-') . '%')
            ->update([
                'status' => WorkerRecord::WORKER_RECORD_APPROVED
            ]);

		//история
		activity()
			->performedOn($workplace)
			->causedBy(Auth::user())
			->withProperties(['customProperty' => 'customValue'])
			->log('отключен месец за дата '.$date.' и обект '.$workplace->name);

        return redirect()->route('service.presence.show.workplace.date', ['workPlaceId' => $workPlaceId, 'date' => $date]);
    }

    private function archiveWorkPlaceForMonth($workPlaceId, $date)
    {
        $jsonData = $this->generateJSONForArchive($workPlaceId, $date);

        $archive = new Archive;

        $archive->work_place_id = $workPlaceId;
        $archive->date = date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-d');
        $archive->json_data = $jsonData;

        $archive->save();
    }

    public function exportDetailedPdf($workPlaceId, $selectedDate)
    {
        $selectedDateArr = explode('-',$selectedDate);
        $month = $selectedDateArr[0];
        $year = $selectedDateArr[1];

        $weekDays = [];

        foreach (range(1, cal_days_in_month(CAL_GREGORIAN, $month, $year)) as $day) {

            if (date('N', strtotime($day . '-' . $month . '-' . $year)) >= 6) {
                $weekDays[] = $day;
            }
        }

        $monthDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $selectedWorkPlace = WorkPlace::findOrFail($workPlaceId);


        $selectedWorkPlaceActivities = WorkPlaceActivity::where('work_place_id','=',$selectedWorkPlace->id)
            ->where(function($q) use ($selectedDate) {

                $date = date_format(date_create_from_format('d-m-Y', '01' . '-' . $selectedDate), 'Y-m-d');

                $q->where('date', $date);
            })
            ->get();

        $tableData = [];

        $tableData = $this->prepareTableData($selectedWorkPlaceActivities, $selectedDate, $selectedWorkPlace);
        $waitingApprovalsCount = 0;
        if ($waitingApprovalsCount > 0) {
            $tableNotEditable = 'true';
        }

        $pdf = PDF::loadView('service::presence.print_template', ['tableData'	=> $tableData,
            'monthDays'				=> $monthDays,
            'weekDays'				=> $weekDays,
            'selectedDate'			=> $selectedDate],[
            'format' => 'A4']);
        return $pdf->download('присъственаФорма-'.$selectedWorkPlace->name.'-'.$selectedDate.'.pdf');

    }

    public function destroyActivity($id)
    {
        $workplaceActivity = WorkPlaceActivity::find($id);
        $dates = explode('-', $workplaceActivity->date);

        //история
        activity()
            ->performedOn($workplaceActivity)
            ->causedBy(Auth::user())
            ->withProperties(['customProperty' => 'customValue'])
            ->log('изтрита дейност: '.$workplaceActivity->activity.' за обект '.$workplaceActivity->workplace->name);

        WorkPlaceActivity::destroy($id);
        return redirect('service/presence/config/'.$workplaceActivity->work_place_id.'/'.$dates[1].'-'.$dates[0])->with('flash_message', 'Изтрихте дейността!');
    }

    private function generateJSONForArchive($workPlaceId, $date)
    {
        $selectedWorkPlaceActivities = WorkPlaceActivity::where('work_place_id','=', $workPlaceId)
            ->where('date' , '!=', null)
            ->where(function($q) use ($date) {

                $formattedDate = date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-d');

                $q->where('date', $formattedDate)
                    ->orWhere('date', null);
            })
            ->get();

        if ($selectedWorkPlaceActivities->count() > 0) {

            $waitingApprovals = Approvement::where('status', Approvement::STATUS_NEW)
                ->where('work_place_id', $workPlaceId)
                ->where('date', 'like', date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-') . '%')
                ->get();

            if ($waitingApprovals->count() > 0) {
                foreach ($waitingApprovals as $waitingApproval) {
                    $this->approvementDisapprove($waitingApproval);
                }
            }

            $workPlace = WorkPlace::find($workPlaceId);

            $tableData = $this->prepareTableData($selectedWorkPlaceActivities, $date, $workPlace);

            return json_encode($tableData);
        }
    }

    private function createApproveRequest($workPlaceId, $date, $overBudget)
    {
        $approveRequest = new Approvement();
        $approveRequest->work_place_id = $workPlaceId;
        $approveRequest->date = date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-d');
        $approveRequest->creator_id = Auth::user()->id;
        $approveRequest->status = Approvement::STATUS_NEW;
        $approveRequest->type_id = Approvement::TYPE_APPR_OBJECT;
        $approveRequest->sum_above_budget = $overBudget;

        $approveRequest->save();


        $workPlace = WorkPlace::find($workPlaceId);
        $regions = $workPlace->region()->get();

        foreach ($regions as $region) {

            $managers = $region->managers()->get();

            foreach ($managers as $manager) {

                $mail = Mail::to($manager->email);

                $mail->send(new VikiRequestAction( [
                    'reason' => 'повишаване на бюджета',
                    'workerplace'  => $workPlace->name,
                    'userWhoTriggerChange' => Auth::user()->name,
                    'link' => route('service.approvement')
                ]));
            }
        }

        return $approveRequest->id;
    }

    private function saveWorkerRecord($userData, $workPlaceId, $date, $status, $approvalId = null)
    {
        $workerRecordData = [
            'hours' => $userData['hours'],
            'day_count' => 0,
            'status' => $status,
            'start_date' => date("Y-m-d"),
            'end_date' => date("Y-m-d"),
            'creator_id' => auth()->user()->id
        ];

        if ((int)$status !== WorkerRecord::WORKER_RECORD_WAITING) {
            $workerRecordData['old_value'] = $userData['hours'];
        }

        if ($approvalId) {
            $workerRecordData['approvement_id'] = $approvalId;
        }

        $workerRecord = WorkerRecord::updateOrCreate(
            [
                'work_place_activity_id' => $userData['workPlaceActivityId'],
                'worker_id' => $userData['workerId'],
                'work_place_id' => $workPlaceId,
                'date' => date_format(date_create_from_format('d-m-Y', $userData['day'] . '-' . $date), 'Y-m-d')
            ],
            $workerRecordData
        );
    }

    private function checkIfInBudget($workPlaceId, $date, $extraData)
    {
        $workPlaceActivities = WorkPlaceActivity::where('work_place_id','=', $workPlaceId)
            ->where(function($q) use ($date) {
                $date = date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-d');
                $q->where('date', $date);
                    //->orWhere('date', null);
            })
            ->get();

        $workPlaceActivityUsedBudget = [];
        $workPlaceActivityCostForHour = [];
        $workPlaceActivityBudgetBeforeChange = [];
        $extraDataNegativeValueKeys = [];

        foreach ($workPlaceActivities as $workPlaceActivity) {

            $workPlaceActivityWorkers = $this->getWorkPlaceActivityWorkersByDate($workPlaceActivity, $date);

            $workPlaceActivityUsedWorkingHours = 0;
            $workPlaceActivityBudgetBeforeChangeHours = 0;

            foreach ($workPlaceActivityWorkers as $workPlaceActivityWorker) {
                foreach ($workPlaceActivityWorker->workerRecords as $workerRecord) {

                    $dataIsCalculated = false;

                    foreach ($extraData as $key => $extraDatum) {

                        if ($extraDatum['workPlaceActivityId'] == $workPlaceActivity->id
                            && $extraDatum['workerId'] == $workerRecord->worker_id
                            && date_format(date_create_from_format('d-m-Y', $extraDatum['day'] . '-' . $date), 'Y-m-d') == $workerRecord->date
                        ) {
                            $workPlaceActivityUsedWorkingHours += $extraDatum['hours'];
                            $dataIsCalculated = true;

                            if ($extraDatum['hours'] < $workerRecord->hours) {
                                $extraDataNegativeValueKeys[] = $key;
                            }
                            unset($extraData[$key]);
                        }
                    }
                    $workPlaceActivityBudgetBeforeChangeHours += $workerRecord->hours;

                    if (!$dataIsCalculated) {
                        $workPlaceActivityUsedWorkingHours += $workerRecord->hours;
                    }
                }
            }

            foreach ($extraData as $key => $extraDatum) {
                if ($extraDatum['workPlaceActivityId'] == $workPlaceActivity->id) {
                    $workPlaceActivityUsedWorkingHours += $extraDatum['hours'];
                }
            }

            $hourCost = $this->getHourCostOnWorkPlaceActivityByDate($workPlaceActivity, $date);

            $workPlaceActivityCostForHour[$workPlaceActivity->id] = $hourCost;

            $workPlaceActivityUsedBudget[$workPlaceActivity->id] = $workPlaceActivityUsedWorkingHours * $hourCost;

            $workPlaceActivityBudgetBeforeChange[$workPlaceActivity->id] = $workPlaceActivityBudgetBeforeChangeHours * $hourCost;
        }

        $workPlace = WorkPlace::with(['overBudget' => function($q) use($date) {
            $q->where('viki_workplace_month_budget.date', date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-d'));
        }])->find($workPlaceId);

        $workPlaceBudget = $workPlace->getBudgetByDate($date);

        if ($workPlace->overBudget->count() > 0) {
            $workPlaceBudget = $workPlaceBudget + $workPlace->overBudget->first()->sum_up;
        }


        if ($workPlaceBudget < array_sum($workPlaceActivityUsedBudget)) {
            return [
                'inBudget' => false,
                'overBudget' => $this->round_up(array_sum($workPlaceActivityUsedBudget) - $workPlaceBudget, 2),
                'budget' => $workPlaceBudget,
                'workPlaceActivityCostForHour' => $workPlaceActivityCostForHour,
                'freeBudgetBeforeChange' => $workPlaceBudget - array_sum($workPlaceActivityBudgetBeforeChange),
                'dataNegativeValueKeys' => $extraDataNegativeValueKeys
            ];
        }

        return [
            'inBudget' => true,
        ];
    }

    private function round_up($value, $precision)
    {
        $pow = pow ( 10, $precision );
        return ( ceil ( $pow * $value ) + ceil ( $pow * $value - ceil ( $pow * $value ) ) ) / $pow;
    }

    private function getHourCostOnWorkPlaceActivityByDate($workPlaceActivity, $date)
    {
        $workPlaceActivityWorkingHours = $this->getActivityWorkingHoursForDate($workPlaceActivity, $date);

        if ($workPlaceActivityWorkingHours === 0) {
            return 0;
        }

        return ($workPlaceActivity->neto_salary + $workPlaceActivity->social_plus) / $workPlaceActivityWorkingHours;
    }

    private function getWorkPlaceActivitiesPricePerHour($idArr, $date)
    {
        $responseArr = [];

        foreach ($idArr as $id) {
            $responseArr[$id] = $this->getHourCostOnWorkPlaceActivityByIdAndDate($id, $date);
        }

        return $responseArr;
    }

    private function getHourCostOnWorkPlaceActivityByIdAndDate($id, $date)
    {
        $workPlaceActivity = WorkPlaceActivity::find($id);

        $workPlaceActivityWorkingHours = $this->getActivityWorkingHoursForDate($workPlaceActivity, $date);

        return ($workPlaceActivity->neto_salary + $workPlaceActivity->social_plus) / $workPlaceActivityWorkingHours;
    }


	private function addCopiedActivitiesFromWorkplace($commonAct, $year, $month)
	{
		$workplaceActivity = WorkPlaceActivity::createCopied(
								[
									'activity' => $commonAct->activity,
									'copied' => WorkPlaceActivity::COPIED_ACTIVITY,
									'type_working' => $commonAct->type_working,
									'neto_salary' => $commonAct->neto_salary,
									'social_plus' => $commonAct->social_plus,
									'worker_count' => $commonAct->worker_count,
									'date' => $year."-".$month."-01" ,
									'work_place_id' => $commonAct->work_place_id,
									'created_by' =>  Auth::id()
								]
							);
		//add the hours for month for standart working people
		if ($commonAct->type_working == WorkPlaceActivity::WORKING_STANDART) {
			 //used for the autoinsert of standart hours
			  $hours_auto_ins = WorkPlaceActivityHoursPerDay::findHoursPerDayPerActivity($commonAct->id);
			  if(empty($hours_auto_ins)) { $hours_auto_ins = 8; }
			  WorkPlaceActivityHoursPerDay::create($hours_auto_ins, $workplaceActivity->id);
			//get the standart hoours for the workplace activity
			$hoursByMonth = (cal_days_in_month(CAL_GREGORIAN, $month, $year) - count($this->getAllNonWorkingDays($month, $year))) * 8;
			HoursActivityByMonth::updateOrCreate(
					[
					'work_place_activity_id' => $workplaceActivity->id,
					'date' => $year."-".$month."-01",
					],
					[
					'hours_for_person' => $hoursByMonth,
					'created_by' =>  Auth::id()
					]
			);

		}

		return $workplaceActivity;
	}

	private function insertStandartWorkingPeople($workerToAdd, $startDate, $lastDayOfMonth, $commonAct, $workplaceActivity)
	{
		//if ($workerToAdd->type_working == WORKER::WORKING_STANDART) {

			$period =	new DatePeriod(
					new DateTime($startDate),
					new DateInterval('P1D'),
					new DateTime($lastDayOfMonth)
			);

			$hours_auto_ins = WorkPlaceActivityHoursPerDay::findHoursPerDayPerActivity($workplaceActivity->id);
			if(empty($hours_auto_ins)) {
				$hours_auto_ins = 8;
			}
			foreach ($period as $key => $value) {
				if(!$this->isWeekend($value->format('Y-m-d'))) {
					//print_r($value->format('Y-m-d') );
					$workerRecord = WorkerRecord::updateOrCreate(
					   [
						   'work_place_activity_id' => $workplaceActivity->id,
						   'worker_id' => $workerToAdd->id,
						   'work_place_id' => $commonAct->work_place_id,
						   'date' => $value->format('Y-m-d')
					   ],[
						   'hours' => $hours_auto_ins,
						   'day_count' => 0,
						   'status' =>  WorkerRecord::WORKER_RECORD_APPROVED,
						   'start_date' => date("Y-m-d"),
						   'end_date' => date("Y-m-d"),
						   'creator_id' => auth()->user()->id
						]
					   );
				}
				if(!$this->isWeekend($lastDayOfMonth)) {
					$workerRecord = WorkerRecord::updateOrCreate(
						   [
							   'work_place_activity_id' => $workplaceActivity->id,
							   'worker_id' => $workerToAdd->id,
							   'work_place_id' => $commonAct->work_place_id,
							   'date' => $lastDayOfMonth
						   ],[
							   'hours' => $hours_auto_ins,
							   'day_count' => 0,
							   'status' =>  WorkerRecord::WORKER_RECORD_APPROVED,
							   'start_date' => date("Y-m-d"),
							   'end_date' => date("Y-m-d"),
							   'creator_id' => auth()->user()->id
							]
						   );
				}


			}
		//}

	}

	public static function checkTheWorkplaceBudget($requestData, $workplaceId, $date, $activity_id)
	{

		$hourlyRate = WorkPlaceActivity::where('id','=',$activity_id)->first()->neto_salary;
		$socialPlus = WorkPlaceActivity::where('id','=',$activity_id)->first()->social_plus;
		$hoursSent = $requestData['hours_for_person'];
		$hourlyRateTotal = $hourlyRate + $socialPlus;

		$allMonthlyBudgetOnWorkplace = $workPlace = WorkPlace::with(['overBudget' => function($q) use($date) {
			$q->where('viki_workplace_month_budget.date', $date);
		}])->find($workplaceId);

		$workPlaceBudget = $allMonthlyBudgetOnWorkplace->getBudgetByDate($date);

		if ($allMonthlyBudgetOnWorkplace->overBudget->count() > 0) {
			$workPlaceBudget = $workPlaceBudget + $allMonthlyBudgetOnWorkplace->overBudget->first()->sum_up;
		}

		$totalMonthlyBudget = $workPlaceBudget;

		$selectedWorkPlaceActivities = WorkPlaceActivity::where('work_place_id','=', $workplaceId)
			->where('date','=', $date)
			->get();

		$totalActivityCost = 0;

		foreach ($selectedWorkPlaceActivities as $selectedWorkPlaceActivity) {

			$hours = HoursActivityByMonth::where('work_place_activity_id','=',$selectedWorkPlaceActivity->id)
				->where('date','=',$date)
				->get();

			if ($hours->count() > 0) {

				$hoursForPerson = $hours->first()->hours_for_person;

				$totalActivityCost += ($selectedWorkPlaceActivity->neto_salary + $selectedWorkPlaceActivity->social_plus) * $hoursForPerson;
			}
		}

		$totalActivityCost += $hourlyRateTotal * $hoursSent;

		if ($totalActivityCost <= $totalMonthlyBudget) {
			return true;
		} else {
			return false;
		}
	}
}
