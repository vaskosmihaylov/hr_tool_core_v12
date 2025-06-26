<?php

namespace viki\Service\Http\Controllers;

use Illuminate\Routing\Controller;
use viki\Service\Models\Elequent\Client;
use viki\Service\Models\Elequent\Region;
use viki\Service\Models\Elequent\VikiUser;
use viki\Service\Request\ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $keyword = $request->get('search');
        $keywordFirstLower = $this->mb_ucfirst(mb_strtolower($keyword), 'UTF-8');
        $keywordUpperCase = mb_strtoupper($keyword);
        $keywordLowerCase = mb_strtolower($keyword);
        $perPage = 15;
        $addToQuery = '';

        $query = Client::where('id', '!=', 0);

        if (Auth::user()->hasRole('manager')) {
            $query->whereHas('regions', function ($q) {
                $q->whereIn('region_id', VikiUser::getCurrentUserRegionId(Auth::user()->id));
            });
        }

        if (!empty($keyword)) {
            $query->where(function ($query) use ($keyword, $keywordFirstLower, $keywordUpperCase, $keywordLowerCase) {
                $query->where('name', 'LIKE', "%$keyword%");
                $query->orWhere('name', 'LIKE', "%$keywordFirstLower%");
                $query->orWhere('name', 'LIKE', "%$keywordUpperCase%");
                $query->orWhere('name', 'LIKE', "%$keywordLowerCase%");
            });
        }

        $clients = $query->orderBy('name', 'asc')->paginate($perPage);

        return view('service::client.index', [
            'clients' => $clients
        ]);
    }

    private function mb_ucfirst($string, $encoding)
    {
        $strlen = mb_strlen($string, $encoding);
        $firstChar = mb_substr($string, 0, 1, $encoding);
        $then = mb_substr($string, 1, $strlen - 1, $encoding);
        return mb_strtoupper($firstChar, $encoding) . $then;
    }

    public function viewFormClient()
    {
        $clients = Client::all();
        $statuses = Client::clientStatuses();

        if (Auth::user()->hasRole('manager')) {
            $regions = Region::whereIn('id', VikiUser::getCurrentUserRegionId(Auth::user()->id))->get();
        } else {
            $regions = Region::where('status', '=', Region::REGION_ACTIVE)->get();
        }

        return view('service::client.create', [
            'clients' => $clients,
            'statuses' => $statuses,
            'regions' => $regions
        ]);
    }

    public function createClient(ClientRequest $request)
    {
        try {
            if (!$request->has('region')) {
                return Redirect::back()->withErrors(['Избери регион!', 'Избери регион!']);
            }

            if ($request->has('region')) {
                foreach($request->region as $region_id) {
                    $reg = Region::find($region_id);
                    if(empty($reg)) {
                        return Redirect::back()->withErrors(['Няма такъв регион!']);
                    }
                }
            }

            $client = Client::create($request->all());

            foreach($request->region as $region_id) {
                $client->regions()->attach(['region_id' => $region_id]);
            }

            // История
            activity()
                ->performedOn($client)
                ->causedBy(Auth::user())
                ->withProperties(['customProperty' => 'customValue'])
                ->log('създаден клиент: ' . $client->name);

            return redirect()->route('service.client')->with('success', 'Успешно създадохте клиент!');

        } catch (\Illuminate\Database\QueryException $e) {
            return Redirect::back()->withErrors(['Името вече е заето!', 'Името вече е заето!']);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function edit($id)
    {
        $client = Client::findOrFail($id);
        $arrayRegions = [];

        foreach($client->regions as $region) {
            $arrayRegions[] = $region->id;
        }

        $statuses = Client::clientStatuses();

        if (Auth::user()->hasRole('manager')) {
            $regions = Region::whereIn('id', VikiUser::getCurrentUserRegionId(Auth::user()->id))->get();
        } else {
            $regions = Region::where('status', '=', Region::REGION_ACTIVE)->get();
        }

        return view('service::client.edit', [
            'client' => $client,
            'statuses' => $statuses,
            'arrayRegions' => $arrayRegions,
            'regions' => $regions
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int      $id
     * @return void
     */
    public function update(ClientRequest $request, $id)
    {
        try {
            $client = Client::findOrFail($id);
            $client->update($request->all());

            if (!$request->has('region')) {
                return Redirect::back()->withErrors(['Избери регион!', 'Избери регион!']);
            }

            if ($request->has('region')) {
                foreach($request->region as $region_id) {
                    $reg = Region::find($region_id);
                    if(empty($reg)) {
                        return Redirect::back()->withErrors(['Няма такъв регион!']);
                    }
                }
            }

            // Detach old regions
            foreach($client->regions as $oldRegions) {
                $client->regions()->detach(['region_id' => $oldRegions->id]);
            }

            // Attach new ones
            foreach($request->region as $region_id) {
                $client->regions()->attach(['region_id' => $region_id]);
            }

            // История
            activity()->performedOn($client)
                ->causedBy(Auth::user())
                ->withProperties(['customProperty' => 'customValue'])
                ->log('редактиран клиент: ' . $client->name);

            return redirect('service/client')->with('flash_message', 'Клиентът е редактиран успешно!');

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
        $client = Client::findOrFail($id);
        $checkWP = Client::checkActiveWorkplaces($client->workplaces);

        if($checkWP == true) {
            return redirect('service/client')->withErrors(['Този клиент не може да бъде деактивиран, защото има активни обекти!']);
        }

        $client->update(['status' => Client::CLIENT_UNACTIVE]);

        // История
        activity()->performedOn($client)
            ->causedBy(Auth::user())
            ->withProperties(['customProperty' => 'customValue'])
            ->log('деактивиран: ' . $client->name);

        return redirect('service/client')->with('flash_message', 'Клиентът е деактивиран');
    }
}
