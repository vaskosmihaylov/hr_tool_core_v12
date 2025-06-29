@extends('layouts.backend')

@section('content')
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css" rel="stylesheet"/>
    <div class="container-fluid">
        <div class="row">
         @include('service::sidebar')
            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
                <div class="card">
					<div class="card-header">Добавяне работник за месец <b>{{$date}}</b> обект <b> {{$workplaceName}} </b></div>
					<div class="card-body common-form">
					@if($errors->any())
						<p class="text-danger">{{$errors->first()}}</p>
					@endif
					<?php $dateN = explode('-',$date);?>
					<a href="{{ url('service/presence/show', ['workPlaceId'=>$workPlaceId,'date'=>$dateN[0].'-'.$dateN[1]] ) }}" title="Back">
						<button class="btn btn-warning btn-md mb-3">
							<i class="fa fa-arrow-left" aria-hidden="true"></i>
							Назад
						</button>
					</a>
					<form method="POST" action="{{route('service.presence.add.worker',['workPlaceId'=>$workPlaceId,'date'=>$date])}}"  name="rentalForm" role="form" enctype="multipart/form-data" id="edit-form">
						{{ csrf_field() }}
						<div class="form-row">
							<div class="col form-group">
								<label for="region_id" class="control-label mb-2">Работници в региона:</label>
								<select  id="regionSelect" name="worker_id" class="form-control" required="">									
										@foreach ($workers as $object)
										@if (\Illuminate\Support\Facades\Request::old('worker_id') == $object->id)
											<option value="{{old('worker_id')}}" selected> {{ $object->name }} {{ $object->family_name }}</option>
										@else
											<option value="{{ $object->id }}">
												{{$object->name }} {{ $object->middle_name }} {{ $object->family_name }}
											</option>
										@endif
										@endforeach
								</select>
								@if ($errors->has('worker_id'))
									<label class="text-danger" for="worker_id"> {{ $errors->first('worker_id') }} </label>
								@endif
							</div>
							<div class="col">
								<label for="work_place_activity_id" class="control-label mb-2">Дейност</label>
								<select id='workPlace'  name="work_place_activity_id" class="form-control" required="">
									@foreach ($workPlaceActivityByMonth as $object)
										@if (\Illuminate\Support\Facades\Request::old('work_place_activity_id') == $object->id)
											<option value="{{old('work_place_id')}}" selected>  {{ $object->activity }}</option>
										@else
											<option value="{{ $object->id }}">
											{{ $object->activity }}
											</option>
										@endif
									@endforeach
								</select>
							</div>
						</div>
						<div class="form-row">
							<div class="col">
								{!! Form::submit( 'Създай', ['class' => 'btn btn-primary']) !!}
							</div>
						</div>
					</form>
				</div>
                </div>
            </div>
        </div>
    </div>
@endsection

