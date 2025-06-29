<?php
namespace viki\Service\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use \Illuminate\Support\Facades\Redirect;

class HistoryController extends Controller
{
    public function index(Request $request)
    {	
		
		$activities = Activity::where('description','NOT LIKE','App%')
							->orderBy('created_at','DESC')
							->paginate(20);
		
		return view('service::history.index',[
					'activities' => $activities
		]);
	}

	
	
}