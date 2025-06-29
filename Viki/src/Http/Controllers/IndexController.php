<?php
namespace viki\Service\Http\Controllers;

use \Illuminate\Routing\Controller;
use \viki\Service\Models\Elequent\Worker;

class IndexController extends Controller
{
    public function index()
    {
       $workers = Worker::all();
		
		return view('service::worker.index',[
            'workers' => $workers
        ]);
    }

}