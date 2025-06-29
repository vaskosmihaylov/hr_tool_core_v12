<?php

namespace viki\Service\Traits;

use viki\Service\Models\Elequent\SpecialDay;
use viki\Service\Models\Elequent\Worker;
use viki\Service\Models\Elequent\WorkPlaceActivity;

trait PresenceTableTrait
{
    private function prepareTableData($selectedWorkPlaceActivities, $selectedDate, $selectedWorkPlace)
    {
        $tableData = [];

        $totalUsedBudget = 0;

        $workPlaceBudget = $selectedWorkPlace->getBudgetByDate($selectedDate);

        $overBudget = $selectedWorkPlace->overBudget()->where('date', date_format(date_create_from_format('d-m-Y', '01' . '-' . $selectedDate), 'Y-m-d'))->first();

        if ($overBudget) {
            $workPlaceBudget = $workPlaceBudget + $overBudget->sum_up;
        }

        foreach ($selectedWorkPlaceActivities as $selectedWorkPlaceActivity) {

            $workingHours = $this->getActivityWorkingHoursForDate($selectedWorkPlaceActivity, $selectedDate);

            $workPlaceActivityWorkers = $this->getWorkPlaceActivityWorkersByDate($selectedWorkPlaceActivity, $selectedDate);

            $workPlaceActivityUsedWorkingHours = 0;

            foreach ($workPlaceActivityWorkers as $workPlaceActivityWorker) {
                foreach ($workPlaceActivityWorker->workerRecords as $workerRecord) {
                    $workPlaceActivityUsedWorkingHours += $workerRecord->hours;
                }
            }

            $pricePerHour = $workingHours != 0 ?($selectedWorkPlaceActivity->neto_salary + $selectedWorkPlaceActivity->social_plus) / $workingHours : 0;

            $usedBudget = round( $workPlaceActivityUsedWorkingHours * $pricePerHour, 2);

            $totalUsedBudget += $usedBudget;

            $tableData[$selectedWorkPlaceActivity->id] = [
                'workPlaceActivityId' => $selectedWorkPlaceActivity->id,
                'workPlaceActivityName' => $selectedWorkPlaceActivity->activity,
                'workPlaceActivitySalary' => $selectedWorkPlaceActivity->neto_salary + $selectedWorkPlaceActivity->social_plus,
                'workPlaceBudget' => $workPlaceBudget,
                'workPlaceActivityUsedBudget' => $usedBudget,
                'workPlaceActivityMaxBudget' => ($selectedWorkPlaceActivity->neto_salary + $selectedWorkPlaceActivity->social_plus) * $selectedWorkPlaceActivity->worker_count,
                'workPlaceActivityMaxWorkingHours' => $workingHours * $selectedWorkPlaceActivity->worker_count,
                'workPlaceActivityHourPrice' => $pricePerHour,
                'workPlaceActivityWorkers' => $workPlaceActivityWorkers,
                'workPlaceActivityUsedWorkingHours' => $workPlaceActivityUsedWorkingHours,
            ];
        }

        foreach ($tableData as $key => $tableDatum) {
            $tableData[$key]['workPlaceTotalUsedBudget'] = $totalUsedBudget;
        }

        return  $tableData;
    }

    private  function getActivityWorkingHoursForDate($workPlaceActivity, $date)
    {
        $workPlaceActivityHours = $workPlaceActivity
            ->hours()
            ->where(
                'date',
                date_format(
                    date_create_from_format('d-m-Y', '01' . '-' . $date),
                    'Y-m-d'
                )
            )
            ->first();

        if ($workPlaceActivityHours) {

            return $workPlaceActivityHours->hours_for_person;

        } else if ($workPlaceActivity->type_working == WorkPlaceActivity::WORKING_STANDART) {

            list($month, $year) = explode("-", $date);

            return (cal_days_in_month(CAL_GREGORIAN, $month, $year) - count($this->getAllNonWorkingDays($month, $year))) * 8;
        }

        return 0;
    }

    private function getWorkPlaceActivityWorkersByDate($workPlaceActivity, $date)
    {
        $temporaryWorkers = $workPlaceActivity
            ->temporaryWorkers()->with([
                'vacations' => function($q) use($date) {
                    $q->where('start_date', 'like', date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-') . '%')
                        ->orWhere('end_date', 'like', date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-') . '%');
                },
                "workerRecords" => function($q) use($workPlaceActivity, $date) {
                    $q->where('viki_worker_records.work_place_activity_id', '=', $workPlaceActivity->id);
                    $q->where('date', 'like', date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-') . '%' );
                }
            ])
            ->wherePivot('date', date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-d'))
            ->get();

        return Worker::whereHas('workPlaceActivity', function ($q) use ($workPlaceActivity) {
                $q->where('id', '=', $workPlaceActivity->id);
            })->with([
                'vacations' => function($q) use($date) {
                    $q->where('start_date', 'like', date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-') . '%')
                        ->orWhere('end_date', 'like', date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-') . '%');
                },
                "workerRecords" => function($q) use($workPlaceActivity, $date) {
                    $q->where('viki_worker_records.work_place_activity_id', '=', $workPlaceActivity->id);
                    $q->where('date', 'like', date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-') . '%' );
                }
            ])
            ->get()
            ->merge($temporaryWorkers);
    }

    private  function getAllNonWorkingDays($month, $year)
    {
        $specialDays = $this->getSpecialDays($month, $year);
        $weekDays = $this->getWeekDays($month, $year);


        if ($specialDays) {
            foreach ($specialDays as $specialDay) {
                if (!in_array($specialDay, $weekDays)) {
                    $weekDays[] = $specialDay;
                }
            }
        }

        return $weekDays;
    }

    private function getSpecialDays($month, $year)
    {
        $specialDays = SpecialDay::where('date', 'like', date_format(date_create_from_format('d-m-Y', '01-' . $month . '-' . $year), 'Y-m-') . '%' )->get();

        $specialDaysArr = [];

        foreach ($specialDays as $specialDay) {
            $specialDaysArr[] = (int)substr($specialDay->date, strrpos($specialDay->date, '-') + 1);
        }

        return $specialDaysArr;
    }

    private function getWeekDays($month, $year)
    {

        $weekDays = [];

        foreach (range(1, cal_days_in_month(CAL_GREGORIAN, $month, $year)) as $day) {

            if (date('N', strtotime($day . '-' . $month . '-' . $year)) >= 6) {
                $weekDays[] = $day;
            }
        }

        return $weekDays;
    }
}