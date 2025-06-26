@extends('layouts.backend')

@section('content')
    <div class="container-fluid">
        <div class="row main-content">
         @include('service::sidebar')
             <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
                <div class="card">
                    <div class="card-header">
						Работници
					</div>
                    <div class="card-body">
						<div class="table-filters">
							<div class="input-group workers-filter">
								@if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('service/worker/create'))
									<a href="{{ url('service/worker/create') }}" class="btn btn-success btn-md" title="Add New User">
										<i class="fa fa-plus" aria-hidden="true"></i> Създай работник
									</a>
								@endif
								@if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('service/worker/insert_holidays'))
									<a href="{{ url('service/worker/insert_holidays') }}" class="btn btn-link" title="Holiday">
										<i class="fa fa-calendar" aria-hidden="true"></i> Въведи автоматично празниците
									</a>

								@endif
							</div>
							{!! html()->form('GET', '/service/worker')->class('form-inline my-2 my-lg-0 float-right col-lg-7 col-md-7 px-0 justify-content-end')->attribute('role', 'search')->open() !!}
							<select  id="types" name="status" class="form-control col-lg-2 col-md-3 col-sm-12" onchange="this.form.submit()">
							<option value="1" @if($status == 1) selected @endif>
								активни
								</option>
							<option value="2" @if($status == 2) selected @endif>
								неактивни
							</option>
							</select>
							<div>&nbsp;&nbsp;</div>
							<input type="text" class="form-control" name="search" placeholder="Търсене...">
							<span class="input-group-append">
								<button class="btn btn-secondary search-btn" type="submit">
									<i class="fa fa-search"></i>
								</button>
							</span>
						</div>
                        {!! html()->form()->close() !!}
					@if ($message = Session::get('success'))

						<div class="alert alert-success alert-block">

							<button type="button" class="close" data-dismiss="alert">×</button>

							<strong>{{ $message }}</strong>

						</div>

					@endif
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Име</th>
										<th>Презиме</th>
										<th>Фамилия</th>
										<th>ЕГН</th>
										<th style="white-space: nowrap">0т дата</th>
										<th>Работно време</th>
										<th>Раб. време договор(h)</th>
										<th>Нетна заплата</th>
										<th>Осигурителен доход</th>
										<th>Регион</th>
										<th style="white-space: nowrap">Основен обект</th>
										<th>Статус</th>
										<th>Опции</th>
									</tr>
								</thead>
								<tbody>
								@foreach($workers as $item)
									<tr>
										<td>{{$item->name}}</td>
										<td>{{$item->middle_name}}</td>
										<td>{{$item->family_name}}</td>
										<td>{{$item->egn}}</td>
										<td>{{$item->start_date}}</td>
										<td>@if($item->type_working == 1) сумарно @else  стандартно	@endif</td>
										<td>{{$item->hours_per_day}}</td>
										<td>{{$item->neto_salary}}</td>
										 <td>{{$item->income}}</td>
										<td>{{$item->region->name}} </td>
										<td>@if (isset($item->workplace)) {{$item->workplace->name}} @endif</td>
										<td>
										@if($item->status == 1)
											<span style="font-size: 18px; color: red">
												<i class="fa fa-user-times" aria-hidden="true"></i>
											</span>
										@elseif($item->status == 4)
											<span style="font-size: 18px; color: green">
												<i class="fa fa-plane" aria-hidden="true"></i>
											</span>
										@elseif($item->status == 3)
											<span style="font-size: 18px; color: red">
												<i class="fa fa-user-hospital" aria-hidden="true"></i>
											</span>
										@elseif($item->status == 0)
											<span style="font-size: 18px; color: green">
												<i class="fa fa-user" aria-hidden="true"></i>
											</span>
										@endif
										</td>
										<td>

												<a href="{{ url('/service/worker/bonus/'.$item->id ) }}" title="Бонус/Наказание">
													<button class="btn btn-warning btn-sm">
														<i class="fa fa-money" aria-hidden="true"></i>
													</button>
												</a>
												@if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('service/worker/create'))
												<a href="{{ url('/service/worker/edit/'.$item->id ) }}" title="Редактирай">
													<button class="btn btn-primary btn-sm">
														<i class="fa fa-pencil-square-o" aria-hidden="true"></i>
													</button>
												</a>
											@endif
											@if ((\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('service/worker/vacation'))
												 &&  ($item->status == 0))
												<a href="{{ url('/service/worker/vacation/'.$item->id ) }}" title="Добави ваканция"><button class="btn  btn-success btn-sm"><i class="fa fa-home fa-o" aria-hidden="true"></i></button></a>
											@endif
										</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div class="pagination"> {!! $workers->appends(['search' => Request::get('search'),'status' => Request::get('status')])->render() !!} </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
