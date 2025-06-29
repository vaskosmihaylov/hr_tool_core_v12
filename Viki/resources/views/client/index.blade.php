@extends('layouts.backend')

@section('content')
    <div class="container-fluid">
        <div class="row main-content">
            @include('service::sidebar')
           <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
                <div class="card">
                    <div class="card-header">Клиенти</div>
                    <div class="card-body">
						@if($errors->any())
							<p class="text-danger">{{$errors->first()}}</p>
						@endif
						<div class="table-filters">
                            <a href="{{ url('service/client/create') }}" class="btn btn-success btn-md" title="Add New User">
                                <i class="fa fa-plus" aria-hidden="true"></i> Създай клиент
                            </a>
                            {!! Form::open(['method' => 'GET', 'url' => '/service/client', 'class' => 'form-inline my-2 my-lg-0 float-right col-md-4 px-0 justify-content-end', 'role' => 'search'])  !!}
                            <input type="text" class="form-control" name="search" placeholder="Търсене...">
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
                                        <th>Клиент</th>
                                        <th>Бюджет</th>	
                                        <th>Регион</th>	
                                        <th>Статус</th>
                                        <th>Опции</th>
                                    </tr>
                                </thead>
                                <tbody>
                               @foreach($clients as $item)
                                    <tr>
                                        <td>{{$item->name}}</td>
                                        <td>{{$item->budget}}</td>
                                        <td>
                                            @foreach($item->regions as $clientRegions)
                                                {{$clientRegions->name}}
                                            @endforeach
                                        </td>
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
                                            <a href="{{ url('/service/client/edit/' . $item->id) }}" title="Редактирай">
                                                <button class="btn btn-primary btn-sm">
                                                    <i class="fa fa-pencil-square-o" aria-hidden="true"></i>
                                                </button>
                                            </a>
                                           {!! Form::open([
                                                'method' => 'DELETE',
                                                'url' => ['/service/client/delete', $item->id],
                                                'style' => 'display:inline'
                                            ]) !!}
                                                {!! Form::button('<i class="fa fa-trash-o" aria-hidden="true"></i>', array(
                                                        'type' => 'submit',
                                                        'class' => 'btn btn-danger btn-sm',
                                                        'title' => 'Изтрий',
                                                        'onclick'=>'return confirm("Сигурни ли сте?")'
                                                )) !!}
                                            {!! Form::close() !!}
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div class="pagination"> {!! $clients->appends(['search' => Request::get('search')])->render() !!} </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
