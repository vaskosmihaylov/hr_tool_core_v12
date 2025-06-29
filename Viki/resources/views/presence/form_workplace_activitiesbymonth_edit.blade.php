@extends('layouts.backend')
@section('content')
<div class="container-fluid">
	<div class="row">
	 @include('service::sidebar')
		<div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
			<div class="card">
				<div class="card-header">Редактирай дейноста</div>
				
					<div class="card-body common-form">
					@if($errors->any())
						<p class="text-danger">{{$errors->first()}}</p>
					@endif
					<a href="{{ url('/service/presence') }}" title="Back">
						<button class="btn btn-warning btn-md mb-3">
							<i class="fa fa-arrow-left" aria-hidden="true"></i>
							Назад
						</button>
					</a>
					
					<form method="POST" action="{{route('service.presence.activity.update',['id'=>$workplaceActivity->id,'date'=>$date])}}" 
						   name="ATForm2" 
						  role="form2" enctype="multipart/form-data" id="edit-form2">
						{{ csrf_field() }}
						<div class="form-group">
							<label for="activity" class="control-label mb-2">Дейност</label>
							<input disabled
							type="text" 
							name="activity" 
							maxlength="50"
							id="activity"
							value="{{ $workplaceActivity->activity }}" 
							class="form-control">

						@if ($errors->has('activity'))
							<label  class="text-danger" for="egn"> {{ $errors->first('activity') }} </label>
						@endif
						</div>
						@if(!empty($workplaceActivity->date))
							<div class="form-group">
								<label for="activity" class="control-label mb-2">Брой работници</label>
								<input
									class="form-control input-sm"
									type="text" 
									name="worker_count" 
									maxlength="10"
									id="worker_count"
									min="1"
									max="100"
									pattern="^[0-9]+" 
									title="само цифри" 
									value="{{ $workplaceActivity->worker_count }}" >
								{!! $errors->first('worker_count', '<p class="text-danger">:message</p>') !!}
							</div>
							<div class="form-row">
								<div class="col form-group">
									<div class="col-sm-15">
										<label class="control-label mb-2">Нето цена</label>
										<div class="input-field">
											<input
												type="decimal" 
												name="neto_salary" 
												id="neto_salary"
												min ="0"
												step=".01"
												pattern ='^\d*(\.\d{1,3})?$'
												max ="1000000"
												value="{{ $workplaceActivity->neto_salary }}" 
												class="form-control">
										</div>
										@if ($errors->has('neto_salary'))
											<label class="text-danger" for="neto_salary"> {{ $errors->first('neto_salary') }} </label>
										@endif
									</div>
								</div>
								<div class="col form-group">
									<div class="col-sm-15">
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
												value="{{ old('social_plus',$workplaceActivity->social_plus) }}" 
												class="form-control">
										</div>
										@if ($errors->has('social_plus'))
											<label class="text-danger" for="social_plus"> {{ $errors->first('social_plus') }} </label>
										@endif
									</div>
								</div>
							</div>
						@endif
						<div class="form-row">
							<div class="col form-group">
								<label  class="control-label mb-2">Часове</label>
								<input  id="hours_for_person" type="number" name='hours_for_person' required="" value="{{ old('hours_for_person',$hour) }}" 
									min="1"  max="300">
								@if ($errors->has('hours_for_person'))
								<label  class="text-danger" for="hours_for_person"> {{ $errors->first('hours_for_person') }} </label>
							@endif
							</div>
						</div>
							<div class="form-group action__btn">
								{!! Form::submit('Запази', ['class' => 'btn btn-primary']) !!}
							</div>
						</form>
                    </div>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection