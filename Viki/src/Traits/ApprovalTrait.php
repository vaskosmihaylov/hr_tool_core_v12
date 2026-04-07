<?php

namespace viki\Service\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use viki\Service\Mail\VikiSendMails;
use viki\Service\Models\Elequent\Approvement;
use viki\Service\Models\Elequent\WorkerRecord;
use viki\Service\Models\Elequent\WorkPlaceMonthBudget;

trait ApprovalTrait
{
    protected function approvementApprove(Approvement $approvement)
    {
        $approvement->update([
            "status" => Approvement::STATUS_APPROVED,
        ]);

        $workerRecords = WorkerRecord::where(
            "approvement_id",
            "=",
            $approvement->id
        )->get();

        $oldSum = WorkPlaceMonthBudget::where(
            "work_place_id",
            $approvement->work_place_id
        )
            ->where("date", $approvement->date)
            ->first();

        $sumUpdate = $approvement->sum_above_budget;

        if (!empty($oldSum)) {
            $sumUpdate = $oldSum->sum_up + $approvement->sum_above_budget;
        }

        WorkPlaceMonthBudget::updateOrCreate(
            [
                "work_place_id" => $approvement->work_place_id,
                "date" => $approvement->date,
            ],
            [
                "sum_up" => $sumUpdate,
                "created_by" => Auth::id(),
            ]
        );

        foreach ($workerRecords as $workerRecord) {
            $workerRecord->update([
                "status" => WorkerRecord::WORKER_RECORD_APPROVED,
                "old_value" => $workerRecord->hours,
            ]);
        }

        $mail = Mail::to($approvement->creator->email);
        $mail->send(
            new VikiSendMails([
                "workerplace" => $approvement->workplace->name,
                "date" => $approvement->date,
                "approved_by" => Auth::user()->name,
                "approve_disapprove" => "одобрена",
            ])
        );

        activity()
            ->performedOn($approvement)
            ->causedBy(Auth::user())
            ->withProperties(["customProperty" => "customValue"])
            ->log(
                "одобри искане: " .
                    $approvement->workplace->name .
                    " от дата " .
                    $approvement->date .
                    " за обект " .
                    $approvement->workplace->name
            );
    }

    protected function approvementDisapprove(Approvement $approvement)
    {
        $approvement->update([
            "status" => Approvement::STATUS_UNAPPROVED,
        ]);

        $workerRecords = WorkerRecord::where(
            "approvement_id",
            "=",
            $approvement->id
        )->get();

        $valueForUpdate = 0;

        foreach ($workerRecords as $workerRecord) {
            if ($workerRecord->old_value > 0) {
                $valueForUpdate = $workerRecord->old_value;
            }

            $workerRecord->update([
                "status" => WorkerRecord::WORKER_RECORD_DISAPPROVED,
                "hours" => $valueForUpdate,
            ]);
        }

        if ($approvement->creator && $approvement->workplace) {
            $approvedByName = Auth::user()?->name ?? "System";

            $mail = Mail::to($approvement->creator->email);
            $mail->send(
                new VikiSendMails([
                    "workerplace" => $approvement->workplace->name,
                    "date" => $approvement->date,
                    "approved_by" => $approvedByName,
                    "approve_disapprove" => "НЕодобрена",
                ])
            );

            activity()
                ->performedOn($approvement)
                ->causedBy(Auth::user())
                ->withProperties(["customProperty" => "customValue"])
                ->log(
                    "неодобри искане: " .
                        $approvement->workplace->name .
                        " от дата " .
                        $approvement->date .
                        " за обект " .
                        $approvement->workplace->name
                );
        }
    }
}
