<?php
namespace viki\Service\Http\Controllers;

use viki\Service\Models\Elequent\Region;
use viki\Service\Models\Elequent\VikiUser;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Role;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $keyword = $request->get('search');
        $perPage = 15;

        if (!empty($keyword)) {

            //check if user is manager and if so show only supervisors from the same region
            if (Auth::user()->hasRole('manager')) {

                $users = VikiUser::withTrashed()
                    ->whereHas('roles', function ($q) {
                        $q->where('name', '=', 'supervisor');
                    })
                    ->whereHas('regions', function ($q) {
                        $q->where('id', '=', $this->getCurrentUserRegionId());
                    })
                    ->where(function ($query) use ($keyword) {
                        $query->where('name', 'LIKE', "%$keyword%")
                            ->orWhere('email', 'LIKE', "%$keyword%");
                    })
                    ->where('id', '!=', auth()->id())
                    ->latest()
					->orderBy('name', 'asc')
                    ->paginate($perPage);

            } else {

                $users = VikiUser::withTrashed()
                    ->whereHas('roles', function($q) {
                        $q->where('name', '!=', 'admin');
                    })
                    ->where(function ($query) use ($keyword) {
                        $query->where('name', 'LIKE', "%$keyword%")
                              ->orWhere('email', 'LIKE', "%$keyword%");
                    })
                    ->where('id', '!=', auth()->id())
                    ->latest()
					->orderBy('name', 'asc')
                    ->paginate($perPage);
            }

        } else {

            if (Auth::user()->hasRole('manager')) {

                $users = VikiUser::withTrashed()
                    ->whereHas('roles', function ($q) {
                        $q->where('name', '=', 'supervisor');
                    })
                    ->whereHas('regions', function ($q) {
                        $q->whereIn('id', $this->getCurrentUserRegionId());
                    })
                    ->where('id', '!=', auth()->id())
                    ->latest()
					->orderBy('name', 'asc')
                    ->paginate($perPage);
            } else {

                $users = VikiUser::withTrashed()
                    ->whereHas('roles', function ($q) {
                        $q->where('name', '!=', 'admin');
                    })
                    ->where('id', '!=', auth()->id())
                    ->latest()
					->orderBy('name', 'asc')
                    ->paginate($perPage);
            }
        }

        return view('service::users.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
        if (Auth::user()->hasRole('manager')) {

            $disableSelect = false;

            $roles = Role::where('name' , 'supervisor')->firstOrFail();

            $user_roles[] = $roles->name;

            $roles = [
                $roles->name => $roles->label
            ];

           
            $regions = Region::select('id', 'name')->whereIn('id', $this->getCurrentUserRegionId())->get();
            $regions = $regions->pluck('name', 'name');
            $regionsT = $this->getCurrentUserRegionId();
            foreach($regionsT as $reg) {          
              $reg  = Region::find($reg);
              $workPlaces[$reg->name] = $reg->workplace()->get()->pluck('name', 'name'); 
            }
           
          return view('service::users.create', compact('roles','regions', 'workPlaces'));

        }     

        $roles = Role::select('id', 'name', 'label')->where('name','NOT LIKE','admin%')->get();
        $roles = $roles->pluck('label', 'name');

        $regions = Region::select('id', 'name')->where('status', Region::REGION_ACTIVE)->get();

        $workPlaces = [];
        foreach ($regions as $region) {
            $workPlaces[$region->name] = $region->workplace()->get()->pluck('name', 'name');
        }

        $regions = $regions->pluck('name', 'name');


        return view('service::users.create', compact('roles','regions', 'workPlaces'));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $this->validate(
            $request,
            [
                'name' => 'required',
                'email' => 'required|string|max:255|email|unique:users',
                'password' => 'required',
                'roles' => 'required',
                'regions' => 'required'
            ]
        );

        $data = $request->except('password');
        $data['password'] = bcrypt($request->password);
        $user = VikiUser::create($data);

        foreach ($request->roles as $role) {
            $user->assignRole($role);
        }

        foreach ($request->regions as $region) {
            $user->assignRegion($region);
        }

        if ($request->workPlaces) {
            foreach ($request->workPlaces as $workPlace) {
                $user->assignWorkPlace($workPlace);
            }
        }
		//история
		activity()
			->performedOn($user)
			->causedBy(Auth::user())
			->withProperties(['customProperty' => 'customValue'])
			->log('създадохте нов потребител: '.$user->name.'с роля '.$role);

        return redirect('service/users')->with('flash_message', 'Потребителя е добавен');
    }

    public function edit($id)
    {
		$roles = Role::select('id', 'name', 'label')->where('name','NOT LIKE','admin%')->get();
        $roles = $roles->pluck('label', 'name');

        $regions = Region::select('id', 'name')->where('status', Region::REGION_ACTIVE)->get();

        $workPlaces = [];
        foreach ($regions as $region) {
            $workPlaces[$region->name] = $region->workplace()->get()->pluck('name', 'name');
        }

        $regions = $regions->pluck('name', 'name');

        $user = VikiUser::with('roles', 'regions', 'workPlaces')->select('id', 'name', 'email')->findOrFail($id);

        $user_roles = [];
        foreach ($user->roles as $role) {
            $user_roles[] = $role->name;
        }

        $user_regions = [];
        foreach ($user->regions as $region) {
            $user_regions[] = $region->name;
        }

        $user_work_places = [];
        foreach ($user->workPlaces as $workPlace) {
            $user_work_places[$workPlace->name] = $workPlace->name;
        }

        if (Auth::user()->hasRole('manager')) {
            $disableSelect = true;
        } else {
            $disableSelect = false;
        }


        return view('service::users.edit', compact('user', 'roles', 'user_roles', 'regions', 'user_regions', 'disableSelect', 'workPlaces', 'user_work_places'));

    }

    /**
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, $id)
    {
        $this->validate(
            $request,
            [
                'name' => 'required',
                'email' => 'required|string|max:255|email|unique:users,email,' . $id,
                'roles' => 'required',
                'regions' => 'required'
            ]
        );

        $data = $request->except('password');
        if ($request->has('password') && $request->password != '') {
            $data['password'] = bcrypt($request->password);
        }

        $user = VikiUser::findOrFail($id);
        $user->update($data);

        $user->roles()->detach();
        $user->regions()->detach();
        $user->workPlaces()->detach();

        foreach ($request->roles as $role) {
            $user->assignRole($role);
        }

        foreach ($request->regions as $region) {
            $user->assignRegion($region);
        }

        if ($request->workPlaces) {
            foreach ($request->workPlaces as $workPlace) {
                $user->assignWorkPlace($workPlace);
            }
        }
		//история
		activity()
			->performedOn($user)
			->causedBy(Auth::user())
			->withProperties(['customProperty' => 'customValue'])
			->log('редактиран потребител: '.$user->name.'с роля '.$role);
		
        return redirect('service/users')->with('flash_message', 'Потребителя е обновен');
    }

    /**
     * @param $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy($id)
    {	
		$vikiUser = VikiUser::find($id);
        VikiUser::destroy($id);
		//история
		activity()
			->performedOn($vikiUser)
			->causedBy(Auth::user())
			->withProperties(['customProperty' => 'customValue'])
			->log('деактивиран потребител: '.$vikiUser->name);
		
        return redirect('service/users')->with('flash_message', 'Потребителя е деактивиран');
    }

    /**
     * @param $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function restore($id)
    {
        VikiUser::withTrashed()->find($id)->restore();
		$vikiUser = VikiUser::find($id);
		//история
		activity()
			->performedOn($vikiUser)
			->causedBy(Auth::user())
			->withProperties(['customProperty' => 'customValue'])
			->log('потребителят беше отново активиран: '.$vikiUser->name);
		
        return redirect('service/users')->with('flash_message', 'Потребителя е активиран');
    }

    private function getCurrentUserRegionId()
    {
        $vikiUser = VikiUser::find(Auth::user()->id);
        $regions = $vikiUser->regions()->get();
        $regionsIds = [];
        foreach ($regions as $region) {
          $regionsIds[] = $region->id;
        }
        return $regionsIds;
    }

    private function getCurrentUserRoleId()
    {
        return Auth::user()->roles()->get()[0]->id;
    }
}