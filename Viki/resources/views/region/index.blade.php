@extends('layouts.backend')

@section('content')
    <div class="container-fluid">
        <div class="row main-content">
             @include('service::sidebar')
             <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
                <div class="card">
                    <div class="card-header">Региони</div>
                    <div class="card-body">
                        <div class="table-filters">
                            @if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('service/region/create'))
                                <a href="{{ url('service/region/create') }}" class="btn btn-success btn-md" title="Добави регион">
                                    <i class="fa fa-plus" aria-hidden="true"></i> Създай регион
                                </a>
                            @endif
                            {!! Form::open(['method' => 'GET', 'url' => '/service/region', 'class' => 'form-inline my-2 my-lg-0 float-right col-lg-3 col-md-4 px-0 justify-content-end', 'role' => 'search'])  !!}
                            <input type="text" class="form-control" name="search" placeholder="Търсене...">
                            <span class="input-group-append">
                                <button class="btn btn-secondary search-btn" type="submit">
                                    <i class="fa fa-search"></i>
                                </button>
                            </span>
						</div>
                        {!! Form::close() !!}
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Име</th>
                                        <th>Мениджър</th>
                                        <th>Статус</th>
                                        <th>Опции</th>
                                    </tr>
                                </thead>
                                <tbody>
                               @foreach($regions as $item)
                                    <tr>
                                        <td>{{$item->name}}</td>
                                        <td>
                                        @foreach($item->managers->toArray() as $managers)
                                        <?php 
                                          $isManager = '';
                                         $ismanager = viki\Service\Models\Elequent\VikiUser::isManager($managers['id']);
                                         ?>
                                          @if ((!empty($managers)) && empty($managers['deleted_at']) && ($ismanager == 'isManager'))
                                          {{$managers['name']}} 
                                          @endif  
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
										@if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('service/region/edit'))
											<td>
                                                <a href="{{ url('/service/region/edit/' . $item->id) }}" title="Редактирай">
                                                    <button class="btn btn-primary btn-sm">
                                                        <i class="fa fa-pencil-square-o" aria-hidden="true"></i>
                                                    </button>
                                                </a>
												{!! Form::open([
													'method' => 'DELETE',
													'url' => ['/service/region/delete', $item->id],
													'style' => 'display:inline'
												]) !!}
													{!! Form::button('<i class="fa fa-trash-o" aria-hidden="true"></i>', array(
															'type' => 'submit',
															'class' => 'btn btn-danger btn-sm',
															'title' => 'Изтрий региона',
															'onclick'=>'return confirm("Искате ли да деактивирате региона?")'
													)) !!}
												{!! Form::close() !!}
											</td>
										@endif
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div class="pagination"> {!! $regions->appends(['search' => Request::get('search')])->render() !!} </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
