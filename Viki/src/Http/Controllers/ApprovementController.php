<?php
namespace viki\Service\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use viki\Service\Models\Elequent\Approvement;
use viki\Service\Models\Elequent\VikiUser;
use viki\Service\Models\Elequent\Comment;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\WorkerRecord;
use viki\Service\Models\Elequent\WorkPlaceMonthBudget;
use viki\Service\Mail\VikiSendMails;
use Illuminate\Support\Facades\Mail;
use viki\Service\Traits\ApprovalTrait;


class ApprovementController extends Controller
{
    use ApprovalTrait;

	public function index(Request $request)
	{
		$status =  $request->get('status') ;
		$type_id = $request->get('type_id');
		$workplace_id = $request->get('workplace_id');
		$perPage = 10;
		$addToQuery = '';
		
		$types = Approvement::approvementTypes();
		$statuses = Approvement::approvementStatuses();
		$workplaces = WorkPlace::orderBy('name')->get();
					
		$query = Approvement::where('id','!=', 0); 
		
		
		if (($status!=null)
			&& ($status!='novalue'))
		{	
			$query->where(function ($query) use ($status) {
								$query->where('status', '=', $status);
								});
		}
		if (($type_id!=null)
			&& ($type_id!='novalue'))
		{
			
			$query->where(function ($query) use ($type_id) {
								$query->where('type_id', '=', $type_id);
								});
		}
		
		if (($workplace_id!=null)
			&& ($workplace_id!='novalue'))
		{			
			$query->where(function ($query) use ($workplace_id) {
								$query->where('work_place_id', '=', $workplace_id);
								});
		}
		if (Auth::user()->hasRole('manager')) {
			
			$managerRegion = VikiUser::getCurrentUserRegionId(Auth::user()->id);
			//$workplaces = WorkPlace::where('region_id', '=',$managerRegion)->get();
			$workplaces = WorkPlace::whereIn('region_id', $managerRegion)->get();
			foreach($workplaces as $workplace){
				//if ($managerRegion == $workplace->region_id) {
				if (in_array( $workplace->region_id, $managerRegion)) {
					$workplaceIds[] = $workplace->id;
				}
			}
			if(!empty($workplaceIds)) {
				$query->where(function ($query) use ($workplaceIds) {
								$query->whereIn('work_place_id', $workplaceIds);
								});
			}
		}
		$workplaceIdsS = array();
		if (Auth::user()->hasRole('supervisor')) {
			$user = VikiUser::find(Auth::user()->id);
			$userWorkPlaces = $user->workPlaces()->get();
			foreach($userWorkPlaces as $workplace){
					$workplaceIdsS[] = $workplace->id;
			}
			
			if(!empty($workplaceIdsS)) {
				//$workplaceIdsStrS = implode(",", $workplaceIdsS);
				$query->where(function ($query) use ($workplaceIdsS) {
								$query->whereIn('work_place_id', $workplaceIdsS);
								});
				$workplaces = WorkPlace::whereIn('id',$workplaceIdsS)->get();
			} else {
				$query->where(function ($query) use ($workplaceIdsS) {
								$query->whereIn('work_place_id', $workplaceIdsS);
								});
				$workplaces = WorkPlace::whereIn('id',$workplaceIdsS)->get();
			}
		}
		
		
		
		$approvements = $query->orderBy('created_at','DESC')->paginate($perPage);
		
		
		return view('service::approvement.index',[
			'approvements' => $approvements,
			'types' => $types,
			'statuses' => $statuses,
			'status' => $status,
			'type_id' => $type_id,
			'workplaces' => $workplaces,
			'workplace_id' => $workplace_id,
		]);
	}
	
	public function viewCommentForm($id)
	{
		
		$approvementComments = Comment::where('approvement_id','=',$id)->paginate(10);
		
		
		return view('service::approvement.comment',
			[
				'approvementComments' => $approvementComments,
				'id' => $id
		]);

	}
	
	public function storeComment(Request $request)
	{
		if ((empty($request->comment))
			||(!$request->has('comment'))) {
			
			return Redirect::back()->withErrors(['Добави коментар']);
		}
		
		try{
			
			$comment = Comment::create($request->comment, $request->id);
			//история
			activity()
				->performedOn($comment)
				->causedBy(Auth::user())
				->withProperties(['customProperty' => 'customValue'])
				->log('добавен коментар :'.$request->comment);
			return redirect()->route('service.approvement.comment',$request->id)->with('success', 'Успешно добавихте коментар!');
			
		} catch ( \Illuminate\Database\QueryException $e) {

			return Redirect::back()->withErrors(['Грешка']);
		}

	}
	
	public function approve(Request $request, $id)
	{	
		$approvement = Approvement::find($id);
		
		if ((empty($id))
			||(empty($approvement))) {
			
			return Redirect::back()->withErrors(['Няма такова одобрение']);
		}		
		try{

		    $this->approvementApprove($approvement);
			
			return redirect()->route('service.approvement', $id)->with('success', 'Успешно одобрихте!');
			
		} catch ( \Illuminate\Database\QueryException $e) {

			return Redirect::back()->withErrors(['Грешка']);
		}

	}
	
	
	public function disapprove(Request $request, $id)
	{
		$approvement = Approvement::find($id);
		
		if ((empty($id))
			||(empty($approvement))) {
			
			return Redirect::back()->withErrors(['Няма такова одобрение']);
		}
		
		try{			

		    $this->approvementDisapprove($approvement);
			
			return redirect()->route('service.approvement', $id)->with('success', 'Успешно неодобрихте!');
			
		} catch ( \Illuminate\Database\QueryException $e) {

			return Redirect::back()->withErrors(['Грешка']);
		}

	}
}