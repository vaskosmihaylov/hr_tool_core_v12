<?php

namespace viki\Service\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use viki\Service\Models\Elequent\Approvement;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\WorkPlaceActivity;
use viki\Service\Traits\ApprovalTrait;
use viki\Service\Traits\PresenceTableTrait;

Class Archive extends Command
{

    use PresenceTableTrait, ApprovalTrait;

    protected $signature = 'archive:start';

    protected $description = 'Create archive for older months';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $date = date('m-Y', strtotime(date('Y-m')." -2 month"));

        $workPlaces = DB::table('viki_worker_records')
            ->where('date', 'like', date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-') . '%')
            ->select('work_place_id')
            ->groupBy('work_place_id')
            ->get();

        foreach ($workPlaces as $workPlace) {
            if (! \viki\Service\Models\Elequent\Archive::where('work_place_id', $workPlace->work_place_id)
                ->where('date', date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-d'))
                ->exists())
            {
                $this->archiveWorkPlaceForMonth($workPlace->work_place_id, $date);
            }
        }
        
        Log::info('Archive Cron Job successfully finished.');
    }

    private function archiveWorkPlaceForMonth($workPlaceId, $date)
    {
        $jsonData = $this->generateJSONForArchive($workPlaceId, $date);

        $archive = new \viki\Service\Models\Elequent\Archive;

        $archive->work_place_id = $workPlaceId;
        $archive->date = date_format(date_create_from_format('d-m-Y', '01' . '-' . $date), 'Y-m-d');
        $archive->json_data = $jsonData;

        $archive->save();
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
}