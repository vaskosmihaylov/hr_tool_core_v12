@extends('layouts.backend')

@section('content')
    <div class="container-fluid">
        <div class="row main-content">
            @include('service::sidebar')

             <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
                <div class="card">
                    <div class="card-header">Потребители</div>
                    <div class="card-body">
						<div class="table-filters">
                            @if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('service/users/create'))
                                <a href="{{ url('/service/users/create') }}" class="btn btn-success btn-md" title="Добави">
                                    <i class="fa fa-plus" aria-hidden="true"></i> Добави потребител
                                </a>
                            @endif

                            {!! Form::open(['method' => 'GET', 'url' => '/service/users', 'class' => 'form-inline my-2 my-lg-0 float-right col-md-4 px-0 justify-content-end', 'role' => 'search'])  !!}
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
                                        <th>Име</th>
                                        <th>Имейл</th>
                                        <th>Роля</th>
                                        <th>Регион</th>
                                        <th>Статус</th>
                                        <th>Опции</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($users as $item)
                                    <tr>
                                        <td>{{ $item->name }}</td>
										<td>{{ $item->email }}</td>
                                        <td>
                                            @foreach($item->roles as $userRoles)
                                                {{$userRoles->label}}
                                            @endforeach
                                        </td>
                                        <td>
                                            @foreach($item->regions as $userRegions)
                                                {{$userRegions->name}}
                                            @endforeach
                                        </td>
                                        <td>
                                            @if($item->trashed())
                                                <span style="font-size: 18px; color: red">
                                                    <i class="fa fa-user-times" aria-hidden="true"></i>
                                                </span>
                                            @else
                                                <span style="font-size: 18px; color: green">
                                                    <i class="fa fa-user" aria-hidden="true"></i>
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ url('/service/users/edit', $item->id) }}" title="Промени Потребителя">
                                                <button class="btn btn-primary btn-sm">
                                                    <i class="fa fa-pencil-square-o" aria-hidden="true"></i>
                                                </button>
                                            </a>
                                            @if($item->trashed())

                                                {!! Form::open([
                                                    'method' => 'PATCH',
                                                    'url' => ['/service/users/restore', $item->id],
                                                    'style' => 'display:inline'
                                                ]) !!}
                                                {!! Form::button('<i class="fa fa-user-plus" aria-hidden="true"></i>', array(
                                                        'type' => 'submit',
                                                        'class' => 'btn btn-success btn-sm',
                                                        'title' => 'Активирай Потребителя',
                                                        'onclick'=>'return confirm("Активиране на потребителя ?")'
                                                )) !!}
                                                {!! Form::close() !!}

                                            @else

                                                {!! Form::open([
                                                    'method' => 'DELETE',
                                                    'url' => ['/service/users/delete', $item->id],
                                                    'style' => 'display:inline'
                                                ]) !!}
                                                    {!! Form::button('<i class="fa fa-user-times" aria-hidden="true"></i>', array(
                                                            'type' => 'submit',
                                                            'class' => 'btn btn-danger btn-sm',
                                                            'title' => 'Деактивирай Потребителя',
                                                            'onclick'=>'return confirm("Деактивиране на потребителя ?")'
                                                    )) !!}
                                                {!! Form::close() !!}

                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div class="pagination"> {!! $users->appends(['search' => Request::get('search')])->render() !!} </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
