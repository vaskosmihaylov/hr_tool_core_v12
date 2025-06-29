@extends('layouts.backend')

@section('content')
    <div class="container-fluid">
        <div class="row main-content">
             @include('service::sidebar')
             <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
                <div class="card">
                    <div class="card-header">Одобрения</div>
                    <div class="card-body common-form">
						<div class="table-filters">
						{!! Form::open(['method' => 'GET', 'url' => '/service/approvement', 'class' => 'my-2 my-lg-0 d-flex flex-row','style' => 'width: 100%', 'role' => 'search'])  !!}
						<select  id="types" name="workplace_id" class="form-control col-lg-3 col-md-3 col-sm-4 mr-4" onchange="this.form.submit()">								
							<option value="novalue" @if($workplace_id =='novalue') selected  @endif>Избери обект</option>
							@foreach ($workplaces as $object)
								<option value="{{ $object['id']}}" @if(($workplace_id == $object['id']) and ($workplace_id!=null) and ($workplace_id !='novalue')) selected  @endif  test="{{$workplace_id}}">
									{{$object['name'] }}
								</option>
							@endforeach
						</select>
						<select  id="types" name="status" class="form-control col-lg-3 col-md-3 col-sm-6" onchange="this.form.submit()">
							<option value="novalue" @if($status =='novalue') selected  @endif>Избери статус</option>
							@foreach ($statuses as $object)
								<option value="{{ $object['id']}}" @if(($status == $object['id']) and ($status!=null) and ($status!='novalue')) selected  @endif  test="{{$status}}">
									{{$object['name'] }}
								</option>
							@endforeach
						</select>
                        {!! Form::close() !!}
						</div>
                        <div class="table-responsive">
						@if(count($approvements)>0)
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Одобрение</th>
                                        <th>Създадено на</th>
										<th>За месец</th>
										<th>Надвишение бюджет</th>
										<th>Клиент надвишен</th>
										<th>Бонус на работник</th>
                                        <th>Статус</th>
                                        <th>Опции</th>
                                    </tr>
                                </thead>
                                <tbody>
                               @foreach($approvements as $item)
                                    <tr>
                                        <td>{{isset($item->workplace->name)? $item->workplace->name : '' }}</td>
                                        <td>{{$item->date}}</td>
										<td>{{$item->date}}</td>
										<td>{{$item->sum_above_budget}}</td>
										<td>
											@if($item->type_id == 0)
												<span style="font-size: 18px;">
													заместване
												</span>
											 @elseif ($item->type_id == 1)
												<span style="font-size: 18px;">
													не
												</span>
											@elseif($item->type_id == 2) 
												<span style="font-size: 18px;">
													да
												</span>
											@endif
										</td>
										 <td>
                                @if($item->type_id == 3)
                                <span style="font-size: 18px;">
                                   да
                                </span>
                                 @else
                                <span style="font-size: 18px;">
                                    не
                                </span>
                                @endif
                            </td>
										<td>
											@if($item->status == 2)
												<span style="font-size: 18px; color: red">
													неодобрен
												</span>
											 @elseif ($item->status == 0)
												<span style="font-size: 18px; color: blue">
													нов
												</span>
											 @elseif ($item->status == 1)
												<span style="font-size: 18px; color: green">
													одобрен
												</span>
											@endif
										</td>
										<td>
											@if ($item->status == 0)
												@if ((Auth::user()->hasRole('manager')) || (Auth::user()->hasRole('admin')))
														{!! Form::open([
														'method' => 'POST',
														'url' => ['/service/approvement/approve', $item->id],
														'style' => 'display:inline'
															]) !!} 
															{!! Form::button('<i class="fa fa-thumbs-up" aria-hidden="true"></i>', array(
																	'type' => 'submit',
																	'class'=>"btn  btn-success btn-sm",
																	'title'=>"Одобри",
																	'onclick'=>'return confirm("Сигурни ли сте ,че искате да одобрите?")'
															)) !!}
														{!! Form::close() !!}
												@endif

												@if ((Auth::user()->hasRole('manager')) || (Auth::user()->hasRole('admin')))										
														{!! Form::open([
														'method' => 'POST',
														'url' => ['/service/approvement/disapprove', $item->id],
														'style' => 'display:inline'
															]) !!} 
															{!! Form::button('<i class="fa fa-thumbs-down" aria-hidden="true"></i>', array(
																	'type' => 'submit',
																	'class'=>"btn btn-danger btn-sm",
																	'title'=>"Неодобрявай",
																	'onclick'=>'return confirm("Сигурни ли сте ,че искате да неодобрите?")'
															)) !!}
														{!! Form::close() !!}
												@endif
											@endif
											@if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('/service/approvement/comment/'))											
													<a href="{{ url('/service/approvement/comment/' . $item->id) }}" title="Добави коментар">
														<button class="btn btn-primary btn-sm">
															<i class="fa fa-comment" aria-hidden="true"></i>
														</button>
													</a>
											@endif
										</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
						@else
						<span><b>Все още нямате одобрения във вашият регион !</b></span>
						@endif
                            <div class="pagination"> {!! $approvements->appends(['search' => Request::get('search')])->render() !!} </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
