@extends('layouts.backend')
@section('content')
<div class="container-fluid">
	<div class="row">
	 @include('service::sidebar')
		 <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
			<div class="card">
				<div class="card-header">Редактирай конфигурирация на обекта</div>
				
					<div class="card-body common-form">
					@if($errors->any())
						<p class="text-danger">{{$errors->first()}}</p>
					@endif
					<a href="{{ url('/service/workplace') }}" title="Back">
						<button class="btn btn-warning btn-md mb-3">
							<i class="fa fa-arrow-left" aria-hidden="true"></i>
							Назад
						</button>
					</a>
					<form method="POST" action="{{route('service.workplace.activity.update',$workplaceActivity->id)}}" 
						   name="ATForm" 
						   role="form" enctype="multipart/form-data" id="edit-form">
						{{ csrf_field() }}
						<div class="form-group">
							<label for="activity" class="control-label mb-2">Дейност</label>
							<input 
								disabled
								type="text" 
								name="activity" 
								maxlength="50"
								id="activity"
								value="{{ $workplaceActivity->activity }}" 
								class="form-control"
							>

						@if ($errors->has('activity'))
							<label class="text-danger" for="egn"> {{ $errors->first('activity') }} </label>
						@endif
						</div>
						<div class="form-group">
							<label for="activity" class="control-label mb-2">Брой работници</label>
							<input
								class="form-control input-sm"
								type="text" 
								name="worker_count" 
								maxlength="10"
								id="egn"
								pattern="^[0-9]+" 
								title="само цифри" 
								value="{{ $workplaceActivity->worker_count }}" >	
							{!! $errors->first('worker_count', '<p class="text-danger">:message</p>') !!}
							@if ($errors->has('worker_count'))
								<label class="text-danger" for="egn"> {{ $errors->first('worker_count') }} </label>
							@endif
						</div>
						<div class="form-group">
							<label for="type_working" class="control-label mb-2">Работно време</label>
								<select  name="type_working" class="form-control" id='mselect' onChange="hideHours();">
								@foreach ($typesWork as $typeW)
									<option  value="{{ $typeW['id'] }}" @if ( $workplaceActivity->type_working == $typeW['id']) selected @endif>
									{{ $typeW['name'] }}
									</option>
								@endforeach
							</select>
						</div>
						<div class="form-group" id="div1"  style='display:none'>
						<label for="hours_per_day">Часове на ден:</label>
						<input type="number" id="hours_per_day" name="hours_per_day" min="1" max="12" 	value="{{ $hours_per_day }}"><br><br>
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
											pattern ='^\d*(\.\d{1,5})?$'
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
								<label class="control-label mb-2"> Социален пакет</label>
								<div class="input-field">
									<input
										type="decimal" 
										name="social_plus" 
										id="social_plus"
										min ="0"
										step=".01"
										pattern ='^\d*(\.\d{1,5})?$'
										max ="1000000"
										value="{{ $workplaceActivity->social_plus }}" 
										class="form-control">
								</div>
								@if ($errors->has('social_plus'))
									<label class="text-danger" for="social_plus"> {{ $errors->first('social_plus') }} </label>
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
<script type='text/javascript'>
   document.addEventListener('DOMContentLoaded', function() {
    var mselect  =  document.getElementById("mselect");
      var mselectvalue = mselect.options[mselect.selectedIndex].value;
      var mdivone =  document.getElementById("div1");
      if (mselectvalue == 1) {  
        mdivone.style.display = "none";
      }
       else {   
       mdivone.style.display = "block";
      } 
    }, false);
   function hideHours() {
    var mselect  =  document.getElementById("mselect");
    var mselectvalue = mselect.options[mselect.selectedIndex].value;
    var mdivone =  document.getElementById("div1");
    if (mselectvalue == 1) {  
      mdivone.style.display = "none";
    }
     else {   
     mdivone.style.display = "block";
    }  
}   
</script>