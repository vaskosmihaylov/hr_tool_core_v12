@extends('layouts.backend')

@section('content')
    <div class="container-fluid">
        <div class="row">
            @include('service::sidebar')
            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
                <div class="card">
                    <div class="card-header">
						<h4>История</h4>                        
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Създаден от</th>
                                        <th>Описание</th>
										<th>Дата</th>
                                    </tr>
                                </thead>
                                <tbody>
                               @foreach($activities as $item)
                                    <tr>
                                        <td>{{$item->causer->name}}</td>
										<td>{{$item->description}}</td>
										<td>{{$item->created_at}}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div class="pagination"> {!! $activities->appends(['search' => Request::get('search')])->render() !!} </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
