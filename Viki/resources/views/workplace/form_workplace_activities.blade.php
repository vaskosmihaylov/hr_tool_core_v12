@extends('layouts.backend')

@section('content')
<div class="container-fluid">
	<div class="row">
	 @include('service::sidebar')
		 <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
			<div class="card">
				<div class="card-header">Конфигурирай обекта</div>
					<div class="card-body common-form">
					@if(!empty($successMsg))
					<p class="alert alert-success">
						<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
						{{ $successMsg  }}
					</p>
					@endif
					@if($errors->any())
						<p class="text-danger">{{$errors->first()}}</p>
					@endif
					<a href="{{ url('/service/workplace') }}" title="Back">
						<button class="btn btn-warning btn-md mb-3">
							<i class="fa fa-arrow-left" aria-hidden="true"></i>
							Назад
						</button>
					</a>
					<form method="POST" action="{{route('service.workplace.activity',$workplace->id)}}"
						  name="rentalForm" 
						  role="form" 
						  enctype="multipart/form-data" id="edit-form">
						{{ csrf_field() }}
						<div class="form-group">
						<label class="label-field mb-2">Обект</label>
						<input 
							type="text" 
							value="{{$workplace->name }}" 
							disabled
							class="form-control">
						</div>
						<div class="form-group">
							<label for="activity" class="control-label mb-2">Дейност</label>
							<input required=""
							type="text" 
							name="activity" 
							maxlength="50"
							id="activity"
							value="{{ old('activity') }}" 
							class="form-control">
							{!! $errors->first('activity', '<p class="text-danger">:message</p>') !!}
						</div>
						<div class="form-group">
							<label for="activity" class="control-label mb-2">Брой работници</label>
							<input
								class="form-control input-sm"
								type="text" 
								name="worker_count" 
								maxlength="10"
								min="1"
								max="100"
								id="worker_count"
								pattern="^[0-9]+" 
								title="само цифри" 
								value="{{ old('worker_count') }}" >	
							{!! $errors->first('worker_count', '<p class="text-danger">:message</p>') !!}
						</div>
						<div class="form-group">
							<label for="type_working" class="control-label mb-2">Работно време</label>
							<select  name="type_working" class="form-control" id='mselect' onChange="hideHours();">
								@foreach ($typesWork as $typeH)
									<option value="{{ $typeH['id'] }}" >
									{{ $typeH['name'] }}
									</option>
								@endforeach
							</select>
						</div>
						  <div class="form-group" id="div1">
							<label for="hours_per_day">Часове на ден:</label>
							<input type="number" id="hours_per_day" name="hours_per_day" min="1" max="12"><br><br>
						  </div>
						<div class="form-row">
							<div class="col form-group">
								<div class="col-sm-15">
									<label class="control-label mb-2"> Заплата нето</label>
									<div class="input-field">
										<input
											type="decimal" 
											name="neto_salary" 
											id="neto_salary"
											min ="0"
											step=".01"
											pattern ='^\d*(\.\d{1,3})?$'
											max ="1000000"
											value="{{ old('neto_salary') }}" 
											class="form-control">
									</div>
									@if ($errors->has('neto_salary'))
										<label class="text-danger" for="neto_salary"> {{ $errors->first('neto_salary') }} </label>
									@endif
								</div>
							</div>
							<div class="col form-group">
								<label class="control-label mb-2"> Социален пакет</label>
								<div class="input-field">
									<input
										type="decimal" 
										name="social_plus" 
										id="social_plus"
										min ="0"
										step=".01"
										pattern ='^\d*(\.\d{1,3})?$'
										max ="1000000"
										value="{{ old('social_plus') }}" 
										class="form-control">
								</div>
								@if ($errors->has('social_plus'))
									<label class="text-danger" for="social_plus"> {{ $errors->first('social_plus') }} </label>
								@endif
							</div>
						</div>
						<div class="form-group">
							{!! Form::submit('Запази', ['class' => 'btn btn-primary']) !!}
						</div>
					</form>
					<div class="table-responsive">
						<table class="table table-striped table-hover">
							<thead class="thead-dark">
								<tr>
									<th>Труд-дейност</th>
									<th>Брой работници</th>
									<th>Работно време</th>
									<th>Нето заплата</th>
									<th>Социален пакет</th>
									<th>Обща цена</th>
									<th>Часове на ден</th>
									<th>Опции</th>
								</tr>
							</thead>
							<tbody>
							@foreach($workplaceActivities as $item)
								<tr>
									<td>{{$item->activity}}</td>
									<td>{{$item->worker_count}}</td>
									<td>@if($item->type_working == 1) сумарно @else  стандартно	@endif
									</td>
									<td>{{$item->neto_salary}}</td>
									<td>{{$item->social_plus}}</td>  
									<td>{{(($item->neto_salary + $item->social_plus) * $item->worker_count)}}</td>
									<?php
                    $hours_per_day = '-';
                    $hours_per_day = viki\Service\Models\Elequent\WorkPlaceActivityHoursPerDay::where('work_place_activity_id',$item->id)->first();
                    if(!empty($hours_per_day)) {
                        $hours_per_day = $hours_per_day->hours_per_day;
                    } else {
                      $hours_per_day = '-';
                    }
                  ?>
                  <td>{{$hours_per_day}}</td>
									<td>
										<a href="{{ url('/service/workplace/activity/edit/' . $item->id ) }}" title="Редактирай дейност">
											<button class="btn btn-primary btn-sm">
												<i class="fa fa-pencil-square-o" aria-hidden="true"></i>
											</button>
										</a>
										{!! Form::open([
											'method' => 'DELETE',
											'url' => ['/service/workplace/activity/delete', $item->id],
											'style' => 'display:inline'
										]) !!}
											{!! Form::button('<i class="fa fa-trash-o" aria-hidden="true"></i>', array(
													'type' => 'submit',
													'class' => 'btn btn-danger btn-sm',
													'title' => 'Изтрий дейност',
													'onclick'=>'return confirm("Сигурни ли сте ,че искате да изтриете дейността?")'
											)) !!}
										{!! Form::close() !!}
									</td>
									
								</tr>
							@endforeach
							</tbody>
						</table>
						<div class="pagination"> {!! $workplaceActivities->appends(['search' => Request::get('search')])->render() !!} </div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
<script type='text/javascript'>
   function hideHours() {
    var mselect  =  document.getElementById("mselect");
    var mselectvalue = mselect.options[mselect.selectedIndex].value;
    var mdivone =  document.getElementById("div1");
	 mdivone.style.display = "none";
    if (mselectvalue == 1) {  
      mdivone.style.display = "none";
    }
     else {   
     mdivone.style.display = "block";
    s}  
}   
</script>