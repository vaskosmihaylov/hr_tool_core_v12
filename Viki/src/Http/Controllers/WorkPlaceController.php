<?php

namespace viki\Service\Http\Controllers;

use Illuminate\Routing\Controller;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\Region;
use viki\Service\Models\Elequent\Client;
use viki\Service\Models\Elequent\Worker;
use viki\Service\Models\Elequent\VikiUser;
use viki\Service\Models\Elequent\WorkPlaceMonthlyBudget;
use viki\Service\Models\Elequent\WorkPlaceActivityHoursPerDay;
use viki\Service\Request\WorkPlaceRequest;
use viki\Service\Request\WorkPlaceActivityRequest;
use viki\Service\Models\Elequent\WorkPlaceActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class WorkPlaceController extends Controller
{
    public function index(Request $request)
    {
        $keyword = $request->get('search');
        $keywordFirstLower = $this->mb_ucfirst(mb_strtolower($keyword), 'UTF-8');
        $keywordUpperCase = mb_strtoupper($keyword);
        $keywordLowerCase = mb_strtolower($keyword);
        $perPage = 15;

        $query = WorkPlace::where('id', '!=', 0);

        if (Auth::user()->hasRole('manager')) {
            $managerRegion = VikiUser::getCurrentUserRegionId(Auth::user()->id);
            $query->whereIn('region_id', $managerRegion);
        }

        if (!empty($keyword)) {
            $query->where(function ($query) use ($keyword, $keywordFirstLower, $keywordUpperCase, $keywordLowerCase) {
                $query->where('name', 'LIKE', "%$keyword%");
                $query->orWhere('name', 'LIKE', "%$keywordFirstLower%");
                $query->orWhere('name', 'LIKE', "%$keywordUpperCase%");
                $query->orWhere('name', 'LIKE', "%$keywordLowerCase%");
            });
        }

        $workplaces = $query->orderBy('name', 'asc')->paginate($perPage);

        return view('service::workplace.index', [
            'workplaces' => $workplaces
        ]);
    }

    private function mb_ucfirst($string, $encoding)
    {
        $strlen = mb_strlen($string, $encoding);
        $firstChar = mb_substr($string, 0, 1, $encoding);
        $then = mb_substr($string, 1, $strlen - 1, $encoding);
        return mb_strtoupper($firstChar, $encoding) . $then;
    }

    public function viewFormWorkPlace()
    {
        if (Auth::user()->hasRole('manager')) {
            $managerRegion = VikiUser::getCurrentUserRegionId(Auth::user()->id);
            $regions = Region::whereIn('id', $managerRegion)->get();
        } else {
            $regions = Region::where('status', '=', Region::REGION_ACTIVE)->get();
        }

        $clients = Client::where('status', '=', Client::CLIENT_ACTIVE)->get();
        $statuses = WorkPlace::workPlaceStatuses();

        return view('service::workplace.create', [
            'regions' => $regions,
            'clients' => $clients,
            'statuses' => $statuses,
        ]);
    }

    public function viewFormWorkPlaceActivity($id)
    {
        $workplaceActivities = WorkPlaceActivity::where('work_place_id', '=', $id)->where('date', null)->paginate(5);
        $workplace = WorkPlace::find($id);
        $typesWork = WorkPlaceActivity::workerTypeWorking();

        return view('service::workplace.form_workplace_activities', [
            'workplaceActivities' => $workplaceActivities,
            'workplace' => $workplace,
            'typesWork' => $typesWork,
            'successMsg' => ''
        ]);
    }

    public function createWorkPlaceActivity(WorkPlaceActivityRequest $request, $id)
    {
        $commonPrice = ($request->neto_salary + $request->social_plus) * $request->worker_count;
        $findAllWorkPlaceActivities = WorkPlaceActivity::where('work_place_id', '=', $id)->where('date', null)->get();
        $sum = 0;

        foreach($findAllWorkPlaceActivities as $addedActivity) {
            $sum = $sum + (($addedActivity['neto_salary'] + $addedActivity['social_plus']) * $addedActivity['worker_count']);
        }

        $workplace = WorkPlace::find($id);
        $sumOfWorkplace = $workplace->getBudgetByDate(date("m-Y"));

        if (($sum + $commonPrice) > $sumOfWorkplace) {
            return Redirect::back()
                    ->withErrors(['Добавяйки тази дейност ще надвишите бюджета на обекта!', 'Добавяйки тази дейност ще надвишите бюджета на обекта!'])
                    ->withInput();
        } else {
            $workplaceActivity = WorkPlaceActivity::create($request->all(), $id);

            // История
            activity()
                ->performedOn($workplaceActivity)
                ->causedBy(Auth::user())
                ->withProperties(['customProperty' => 'customValue'])
                ->log('създадена основна дейност: ' . $workplaceActivity->activity . ' за обект ' . $workplace->name);

            if ((WorkPlaceActivity::WORKING_STANDART == $request->type_working) && (!empty($workplaceActivity->id))) {
                if (($request->hours_per_day < 0) || ($request->hours_per_day > 12)) {
                    return Redirect::back()->withErrors(['Часовете трябва да са между 1 и 12!!']);
                }
                WorkPlaceActivityHoursPerDay::create($request->hours_per_day, $workplaceActivity->id);
            }

            return redirect()->route('service.workplace.activity', $id)->with('success', 'Успешно създадохте дейност!');
        }
    }

    public function createWorkPlace(WorkPlaceRequest $request)
    {
        if ($request->client == 'none') {
            return Redirect::back()->withErrors(['Избери клиент!', 'Избери клиент!']);
        }

        $validate = self::validateBudget($request, '', 'create');
        if ($validate == false) {
            return Redirect::back()->withErrors(['Ще надвишите бюджета на клиента!'])->withInput();
        }

        $workplace = WorkPlace::create($request->all());

        // История
        activity()
            ->performedOn($workplace)
            ->causedBy(Auth::user())
            ->withProperties(['customProperty' => 'customValue'])
            ->log('създаден обект: ' . $workplace->name);

        return redirect()->route('service.workplace')->with('success', 'Успешно създадохте обект!');
    }

    public static function validateBudget($request, $id = null, $action)
    {
        if ($request->region == 'none') {
            return Redirect::back()->withErrors(['Избери регион!', 'Избери регион!'])->withInput();
        }

        if ($request->has('region')) {
            $reg = Region::find($request->region);
            if (empty($reg)) {
                return Redirect::back()->withErrors(['Няма такъв регион!']);
            }
        }

        if ($request->client == 'none') {
            return Redirect::back()->withErrors(['Избери клиент!', 'Избери клиент!']);
        }

        if ($request->has('client')) {
            $cl = Client::find($request->client);
            if (empty($cl)) {
                return Redirect::back()->withErrors(['Няма такъв клиент!']);
            }
        }

        if ($request->client == null) {
            $workplace = WorkPlace::findOrFail($id);
            $request->client = $workplace->client_id;
        }

        $clientS = Client::find($request->client);
        $sum = 0;

        if (count($clientS->workplaces) > 0) {
            foreach($clientS->workplaces as $existingWorkPlaces) {
                if ($existingWorkPlaces->status == WorkPlace::WORK_PLACE_ACTIVE) {
                    $sum = $sum + $existingWorkPlaces->getBudgetByDate(date('m-Y'));
                }
            }
        }

        if ($sum > 0) {
            if ($action == 'update') {
                $sum = ($sum - $workplace->getBudgetByDate(date('m-Y'))) + $request->budget;
            } else {
                $sum = $sum + $request->budget;
            }
        }

        if (($clientS->budget < $request->budget) || ($clientS->budget < ($sum))) {
            return false;
        }

        return true;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $workplace = WorkPlace::findOrFail($id);
        $regions = Region::where('status', '=', Region::REGION_ACTIVE)->get();
        $clients = Client::where('status', '=', Client::CLIENT_ACTIVE)->get();
        $statuses = WorkPlace::workPlaceStatuses();
        $budgets = $workplace->monthlyBudget()->orderBy('valid_from', 'desc')->get();

        return view('service::workplace.edit', [
            'regions' => $regions,
            'clients' => $clients,
            'statuses' => $statuses,
            'workplace' => $workplace,
            'budgets' => $budgets
        ]);
    }

    public function editActivity($id)
    {
        try {
            $workplaceActivity = WorkPlaceActivity::find($id);
            $workplace = WorkPlace::find($workplaceActivity->work_place_id);
            $typesWork = WorkPlaceActivity::workerTypeWorking();

            // For standard insert hours per day
            $hours_per_day = 0;
            if ((WorkPlaceActivity::WORKING_STANDART == $workplaceActivity->type_working) && (!empty($workplaceActivity->id))) {
                $hours_per_day = WorkPlaceActivityHoursPerDay::where('work_place_activity_id', '=', $id)->get()->first();
                if (!empty($hours_per_day)) {
                    $hours_per_day = $hours_per_day->hours_per_day;
                }
            }

            return view('service::workplace.form_workplace_activities_edit', [
                'workplaceActivity' => $workplaceActivity,
                'workplace' => $workplace,
                'typesWork' => $typesWork,
                'hours_per_day' => $hours_per_day,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return Redirect::back()->withErrors(['Грешка']);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int      $id
     * @return void
     */
    public function update(WorkPlaceRequest $request, $id)
    {
        try {
            $validate = self::validateBudget($request, $id, 'update');
            if ($validate == false) {
                return Redirect::back()->withErrors(['Надвишавате бюджета за клиента!'])->withInput();
            }

            $workplace = WorkPlace::findOrFail($id);
            $workplace->update($request->all());

            $budgetDate = \DateTime::createFromFormat('d-m-Y H:i:s', '01-' . $request->get('budgetDate') . ' 00:00:00');

            $monthlyBudget = WorkPlaceMonthlyBudget::firstOrNew([
                'viki_work_place_id' => $id,
                'valid_from' => $budgetDate->getTimestamp()
            ]);

            $monthlyBudget->budget = $request->get('budget');
            $workplace->monthlyBudget()->save($monthlyBudget);

            // История
            activity()
                ->performedOn($workplace)
                ->causedBy(Auth::user())
                ->withProperties(['customProperty' => 'customValue'])
                ->log('редактиран обект: ' . $workplace->name);

            return redirect('service/workplace')->with('flash_message', 'Обектът беше редактиран!');

        } catch (\Illuminate\Database\QueryException $e) {
            return Redirect::back()->withErrors(['Грешка']);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int      $id
     * @return void
     */
    public function updateActivity(WorkPlaceActivityRequest $request, $id)
    {
        try {
            // id is the id of activity
            $workplaceActivity = WorkPlaceActivity::findOrFail($id);

            // For standard insert hours per day
            if ((WorkPlaceActivity::WORKING_STANDART == $request->type_working) && (!empty($workplaceActivity->id))) {
                WorkPlaceActivityHoursPerDay::create($request->hours_per_day, $workplaceActivity->id);
            }

            $oldValuePrice = ($workplaceActivity->neto_salary + $workplaceActivity->social_plus) * $workplaceActivity->worker_count;
            $commonPrice = ($request->neto_salary + $request->social_plus) * $request->worker_count;

            $findAllWorkPlaceActivities = WorkPlaceActivity::where('work_place_id', '=', $workplaceActivity->work_place_id)
                                                          ->where('date', null)
                                                          ->get();
            $sum = 0;
            foreach($findAllWorkPlaceActivities as $addedActivity) {
                $sum = $sum + (($addedActivity['neto_salary'] + $addedActivity['social_plus']) * $addedActivity['worker_count']);
            }

            $sum = ($sum - $oldValuePrice) + $commonPrice;
            $workplace = WorkPlace::find($workplaceActivity->work_place_id);
            $sumOfWorkplace = $workplace->getBudgetByDate(date('m-Y'));

            if ($sum > $sumOfWorkplace) {
                return Redirect::back()->withErrors(['Променяйки тази дейност ще надвишите бюджета на обекта!', 'Добавяйки тази дейност ще надвишите бюджета на обекта!']);
            } else {
                $workplaceActivity->update($request->all());

                // История
                activity()
                    ->performedOn($workplaceActivity)
                    ->causedBy(Auth::user())
                    ->withProperties(['customProperty' => 'customValue'])
                    ->log('редактирана основна дейност: ' . $workplaceActivity->activity . ' за обект ' . $workplace->name);

                $workplaceActivities = WorkPlaceActivity::where('work_place_id', '=', $workplace->id)->where('date', null)->paginate(5);
                $typesWork = WorkPlaceActivity::workerTypeWorking();

                return view('service::workplace.form_workplace_activities', [
                    'workplaceActivities' => $workplaceActivities,
                    'workplace' => $workplace,
                    'typesWork' => $typesWork,
                ])->with('successMsg', 'Активността е редактирана!');
            }

        } catch (\Illuminate\Database\QueryException $e) {
            return Redirect::back()->withErrors(['Грешка']);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy($id)
    {
        $workplace = WorkPlace::findOrFail($id);
        $workplace->update(['status' => WorkPlace::WORK_PLACE_UNACTIVE]);

        // История
        activity()
            ->performedOn($workplace)
            ->causedBy(Auth::user())
            ->withProperties(['customProperty' => 'customValue'])
            ->log('деактивиран обект :' . $workplace->name);

        return redirect('service/workplace')->with('flash_message', 'Обектът е деактивиран!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroyActivity($id)
    {
        $workplaceActivity = WorkPlaceActivity::findOrFail($id);
        $workplace = WorkPlace::find($workplaceActivity->work_place_id);

        // Remove workers from the activity
        $findWorkers = Worker::where('work_place_activity_id', '=', $id)->get();

        if (!empty($findWorkers)) {
            foreach($findWorkers as $worker) {
                $worker->update(['work_place_activity_id' => null]);
            }
        }

        // История
        activity()
            ->performedOn($workplaceActivity)
            ->causedBy(Auth::user())
            ->withProperties(['customProperty' => 'customValue'])
            ->log('премахната основна дейност:' . $workplaceActivity->activity . ' от обект ' . $workplace->name);

        // Destroy this activity
        $workplaceActivity->destroy($id);

        return Redirect::back()->with('flash_message', 'Дейността е премахната!');
    }
}
