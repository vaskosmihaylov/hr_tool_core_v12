<?php
namespace viki\Service\Http\Controllers;

use Illuminate\Routing\Controller;
use viki\Service\Models\Elequent\Region;
use viki\Service\Models\Elequent\VikiUser;
use viki\Service\Request\RegionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class RegionController extends Controller
{
	public function index(Request $request)
	{
		$keyword = $request->get('search');
		$perPage = 15;
		$addToQuery = '';
		
		$query = Region::where('id','!=', 0); 
		
		if (Auth::user()->hasRole('manager')) {
			$managerRegion = VikiUser::getCurrentUserRegionId(Auth::user()->id);
			$query->whereIn('id',$managerRegion);
			
		}
		if (!empty($keyword)) {
			
			$query->where(function ($query) use ($keyword) {
								$query->where('name', 'LIKE', "%$keyword%");
								});
		}
		$regions = $query->orderBy('name', 'asc')->paginate($perPage);
		return view('service::region.index',[
			'regions' => $regions
		]);
	}
	
	public function viewFormRegion()
	{
		
		$regions = Region::all();
		$statuses = Region::regionStatuses();
		
		return view('service::region.create',
			[
				'regions' => $regions,
				'statuses' => $statuses,
		]);

	}
	
	public function createRegion(RegionRequest $request)
	{
		
		try{
			$region = Region::create($request->all());
			//история
			activity()
				->performedOn($region )
				->causedBy(Auth::user())
				->withProperties(['customProperty' => 'customValue'])
				->log('създаден регион: '.$region->name);
			
			return redirect()->route('service.region')->with('success', 'Успешно създадохте регион!');
		} catch ( \Illuminate\Database\QueryException $e) {
			
			return Redirect::back()->withErrors(['Грешка-Дубликирано име за регион!', 'Дубликирано име за регион!']);       
		}
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
        $region = Region::findOrFail($id);
		$statuses = Region::regionStatuses();
		
		return view('service::region.edit',
			[
				
				'region'    => $region,
				'statuses' => $statuses,
			]);	
       
    }
	
	/**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int      $id
     *
     * @return void
     */
    public function update(RegionRequest $request, $id)
    {
		try{
        
			$region = Region::findOrFail($id);
			$region->update($request->all());
			//история
			activity()
				->performedOn($region )
				->causedBy(Auth::user())
				->withProperties(['customProperty' => 'customValue'])
				->log('редактиран регион: '.$region->name);
			
			return redirect('service/region')->with('flash_message', 'Регионът е редактиран!');
			
		}  catch ( \Illuminate\Database\QueryException $e) {
			
			return Redirect::back()->withErrors(['Грешка-Дубликирано име за регион!', 'Дубликирано име за регион!']);       
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
        
		$region = Region::findOrFail($id);
        $region->update(['status'=> Region::REGION_UNACTIVE ]);
		//история
		activity()
			->performedOn($region )
			->causedBy(Auth::user())
			->withProperties(['customProperty' => 'customValue'])
			->log('деактивиран регион: '.$region->name);
		
        return redirect('service/region')->with('flash_message', 'Регионът е деактивиран');
    }

}