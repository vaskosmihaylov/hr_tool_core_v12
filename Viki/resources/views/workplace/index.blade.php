@extends('layouts.backend')

@section('content')
    <div class="container-fluid">
        <div class="row main-content">
			@include('service::sidebar')
             <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
                <div class="card">
                    <div class="card-header">Обекти</div>
                    <div class="card-body">
						<div class="table-filters">
                            <a href="{{ url('service/workplace/create') }}" class="btn btn-success btn-md" title="Add New Object">
                                <i class="fa fa-plus" aria-hidden="true"></i> Създай обект
                            </a>
                            {!! Form::open(['method' => 'GET', 'url' => '/service/workplace', 'class' => 'form-inline my-2 my-lg-0 float-right col-md-4 px-0 justify-content-end', 'role' => 'search'])  !!}
                            <input type="text" class="form-control" name="search" placeholder="Търси по име...">
                            <span class="input-group-append">
                                <button class="btn btn-secondary search-btn" type="submit">
                                    <i class="fa fa-search"></i>
                                </button>
                            </span>
                            {!! Form::close() !!}
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Обект</th>
										<th>Адрес</th>
										<th>Клиент</th>
										<th>Регион</th>
										<th>Бюджет</th>
										<th>Статус</th>
										<th>Опции</th>
                                    </tr>
                                </thead>
                                <tbody>
                               @foreach($workplaces as $item)
                                    <tr>
                                        <td>{{$item->name}}</td>
                                        <td>{{$item->address}}</td>
										<td>{{$item->client->name}}</td>
                                        <td>{{$item->region->name}}</td>
										<td>{{$item->budget}}</td>
										<td>
                                        @if($item->status == 1)
                                           <span style="font-size: 18px; color: red">
                                              <i class="fa fa-times" aria-hidden="true"></i>
                                           </span>
                                        @else
                                           <span style="font-size: 18px; color: green">
                                              <i class="fa fa-check" aria-hidden="true"></i>
                                            </span>
                                         @endif
                                        </td>
                                        <td>
                                            <a href="{{ url('/service/workplace/edit/' . $item->id ) }}" title="Редактирай">
                                                <button class="btn btn-primary btn-sm">
                                                    <i class="fa fa-pencil-square-o" aria-hidden="true"></i>
                                                </button>
                                            </a>
                                            {!! Form::open([
                                                'method' => 'DELETE',
                                                'url' => ['/service/workplace/delete', $item->id],
                                                'style' => 'display:inline'
                                            ]) !!}
                                                {!! Form::button('<i class="fa fa-trash-o" aria-hidden="true"></i>', array(
                                                        'type' => 'submit',
                                                        'class' => 'btn btn-danger btn-sm',
                                                        'title' => 'Изтрий',
                                                        'onclick'=>'return confirm("Изтриване?")'
                                                )) !!}
                                            {!! Form::close() !!}
                                            <a href="{{ url('/service/workplace/activity/' . $item->id ) }}" title="Добави дейностти">
                                                <button class="btn  btn-success  btn-sm">
                                                    <i class="fa fa-briefcase" aria-hidden="true"></i>
                                                </button>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div class="pagination"> {!! $workplaces->appends(['search' => Request::get('search')])->render() !!} </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
