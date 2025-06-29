@extends('layouts.backend')

@section('content')
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css" rel="stylesheet"/>
    <div class="container-fluid">
        <div class="row">
         @include('service::sidebar')
             <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
                <div class="card">
					<?php $dateN = explode('-',$date);?>
					<div class="card-header">Настройки присъствена форма - месец {{$dateN[0]}} обект {{$workPlaceName}}</div>
					<div class="card-body common-form">
					@if($errors->any())
						<p class="text-danger">{{$errors->first()}}</p>
					@endif
					
					<div class="table-filters">
						<a href="{{ url('service/presence/show', ['workPlaceId' => $workPlaceId, 'date' => $dateN[0].'-'.$dateN[1]] ) }}" title="Back"><button class="btn btn-warning btn-md"><i class="fa fa-arrow-left" aria-hidden="true"></i>Назад</button></a>									
						<a href="{{ url('service/presence/activity/add', ['workPlaceId' => $workPlaceId, 'date' => $dateN[0].'-'.$dateN[1]] ) }}"><button class="btn btn-success btn-md">Добави дейност</button></a>										
					</div>
					<form method="POST" action="{{route('service.presence.config',['workPlaceId'=>$workPlaceId,'date'=>$date])}}"  name="rentalForm" role="form" enctype="multipart/form-data" id="edit-form">
						{{ csrf_field() }}
					</form>
					<div class="table-responsive">
                        <table class="table table-striped table-hover">
                        <thead class="thead-dark">
							<tr>
								<th>Труд-дейност</th>
								<th> Основна </th>
								<th>Брой работници</th>
								<th>Обща цена(за един)</th>
								<th>Часове</th>
								<th>Опции</th>
							</tr>
						</thead>
                        <tbody>
                               @foreach($workPlaceActivityByMonth as $item)
                                    <tr>
										<td>{{$item->activity}}</td>
										@if ($item->copied == 1)
											<td>да</td>
										@else
											<td>не</td>
										@endif
										<td>{{$item->worker_count}}</td>
										<td>{{$item->neto_salary + $item->social_plus }}</td>
										<td>@if(array_key_exists($item->id,$getHours))
											{{$getHours[$item->id]}}
										   @endif</td>
										<td>
											<a href="{{ url('/service/presence/activity/edit/' .$item->id .'/'.$date) }}" title="Редактирай дейност">
												<button class="btn btn-primary btn-sm">
													<i class="fa fa-pencil-square-o" aria-hidden="true"></i>
												</button>
											</a>
											@if(!empty($item->date))
												{!! Form::open([
													'method' => 'DELETE',
													'url' => ['/service/presence/activity/delete', $item->id],
													'style' => 'display:inline'
												]) !!} 

													{!! Form::button('<i class="fa fa-trash-o" aria-hidden="true"></i>', array(
															'type' => 'submit',
															'class' => 'btn btn-danger btn-sm',
															'title' => 'Изтрий дейност',
															'onclick'=>'return confirm("Сигурни ли сте ,че искате да изтриете дейността?")'
													)) !!}
												{!! Form::close() !!}	
											@endif
										</td>
										
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
				</div>
                </div>
            </div>
        </div>
    </div>
@endsection
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js"></script>
<script src="http://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/bootstrap-datepicker.js"></script>
<script>

</script>
