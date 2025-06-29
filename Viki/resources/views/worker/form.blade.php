<form method="POST" action="{{route('service.worker.create')}}" enctype="multipart/form-data" id="edit-form">
@if($errors->any())
	<p class="text-danger">{{$errors->first()}}</p>
@endif
<div class="form-row">
	<div class="col-lg-4 col-md-6">
		<div class="form-group{{ $errors->has('name') ? ' has-error' : ''}}">
			{!! Form::label('name', 'Име: ', ['class' => 'control-label mb-2']) !!}
			{!! Form::text('name', null, ['class' => 'form-control', 'required' => 'required']) !!}
			{!! $errors->first('name', '<p class="text-danger">:message</p>') !!}
		</div>
	</div>
	<div class="col-lg-4 col-md-6">
		<div class="form-group{{ $errors->has('middle_name') ? ' has-error' : ''}}">
			{!! Form::label('middle_name', 'Презиме: ', ['class' => 'control-label mb-2']) !!}
			{!! Form::text('middle_name', null, ['class' => 'form-control', 'required' => 'required']) !!}
			{!! $errors->first('middle_name', '<p class="text-danger">:message</p>') !!}
		</div>
	</div>
	<div class="col-lg-4 col-md-6">
		<div class="form-group{{ $errors->has('family_name') ? ' has-error' : ''}}">
			{!! Form::label('family_name', 'Фамилия: ', ['class' => 'control-label mb-2']) !!}
			{!! Form::text('family_name', null, ['class' => 'form-control', 'required' => 'required']) !!}
			{!! $errors->first('family_name', '<p class="text-danger">:message</p>') !!}
		</div>
	</div>
</div>
<div class="form-row align-items-start">
	<div class="col">
		<label for="egn" class="control-label mb-2">ЕГН</label>
		<div class="form-group">
			<input
			type="text" 
			name="egn" 
			maxlength="10"
			minlength="10"
			id="egn"
			required
			pattern="^[0-9]+" 
			title="само цифри" 
			value="{{ old('egn') }}" 
			class="form-control">
		</div>
		
	</div>
	<div class="col">
			<label for="start_date" class="control-label mb-2">От дата</label>
			<div class="input-group date" data-provide="datepicker" id="datetimepicker">
				<input name='start_date' type="text" class="form-control"
					   value="{{ old('start_date') }}" 
					   placeholder="YYYY-M-D" 
					   autocomplete="off"
					   id="start_date"
					   required="" data-date-format="YYY-M-D">
				<div class="input-group-addon">
					<span class="glyphicon glyphicon-th"></span>
				</div>
			</div>
			@if ($errors->has('start_date'))
			<label  class="text-danger" for="start_date"> {{ $errors->first('start_date') }} </label>
			@endif	
	</div>
</div>
<div class="form-group">
	<label for="status" class="control-label mb-2">Статус</label>
	<select  name="status" class="form-control" style='width:60%!important' disabled="disabled">
		@foreach ($statuses as $status)	
			<option value="{{ $status['id'] }}">
			{{ $status['name'] }}
			</option>
		@endforeach
    </select>
</div>
<div class="form-row">
	<div class="col form-group">
		<label for="type_working" class="control-label mb-2">Работно време</label>
		<select  name="type_working" class="form-control">
			@foreach ($typesWork as $type)
				@if (\Illuminate\Support\Facades\Request::old('type_working') ==  $type['id'])
					<option value="{{ $type['id'] }}" selected>{{ $type['name'] }}</option>
				@else
					<option value="{{ $type['id'] }}" >
					{{ $type['name'] }}
					</option>
				@endif
			@endforeach
		</select>
	</div>
	<div class="col form-group">
		<label for="hours_per_day" class="control-label mb-2">Часове</label>
		<div class="input-field">
		<input type="number" class="form-control"
				name='hours_per_day' 
				id="hours_per_day"
				required="" 
				value="{{ old('hours_per_day') }}" 
				min="1"  
				max="400">
		</div>		
		@if ($errors->has('hours_per_day'))
			<label  class="text-danger" for="hours_per_day"> {{ $errors->first('hours_per_day') }} </label>
		@endif
	</div>
	<div class="col form-group">
		<label class="control-label mb-2"> Нето заплата</label>
		<div class="input-field">
			<input
				type="decimal" 
				name="neto_salary" 
				id="neto_salary"
				min ="0"
				step=".01"
				pattern ='^\d*(\.\d{1,3})?$'
				max ="100000"
				value="{{ old('neto_salary') }}" 
				class="form-control">
		</div>
		@if ($errors->has('neto_salary'))
			<label class="text-danger" for="neto_salary"> {{ $errors->first('neto_salary') }} </label>
		@endif
	</div>
	 <div class="col form-group">
		<label class="control-label mb-2"> Осигурителен доход</label>
		<div class="input-field">
			<input
				type="decimal" 
				name="income" 
				id="income"
				min ="0"
				step=".01"
				pattern ='^\d*(\.\d{1,2})?$'
				max ="100000"
				value="{{ old('income') }}" 
				class="form-control">
		</div>
		@if ($errors->has('income'))
			<label class="text-danger" for="income"> {{ $errors->first('income') }} </label>
		@endif
	</div>
</div>

<div class="form-row">
	<div class="col form-group">
		<label for="region_id" class="control-label mb-2">Регион</label>
		<select  id="regionSelect" name="region_id" class="form-control" >
				<option value="none">--</option>
				@foreach ($regions as $region)
				@if (\Illuminate\Support\Facades\Request::old('region_id') == $region->id)
					<option value="{{old('region_id')}}" selected> {{ $region->name }}</option>
				@else
					<option value="{{ $region->id }}">
						{{ $region->name }}
					</option>
				@endif
				@endforeach
		</select>
		@if ($errors->has('region_id'))
			<label class="text-danger" for="region_id"> {{ $errors->first('region_id') }} </label>
		@endif
	</div>
	<div class="col form-group">
		<label for="work_place_id" class="control-label mb-2">Обект</label>
		<select id='workPlace'  name="work_place_id" class="form-control" >
			<option value="none">--</option>
			@foreach ($workplaces as $object)
				@if (\Illuminate\Support\Facades\Request::old('work_place_id') == $object->id)
					<option value="{{old('work_place_id')}}" selected>  {{ $object->name }}</option>
				@else
					<option value="{{ $object->id }}" region_id ="{{$object->region_id}}">
					{{ $object->name }}
					</option>
				@endif
			@endforeach
		</select>
	</div>
	<div class="col form-group">
		<label for="work_place_activity_id" class="control-label mb-2">Дейност</label>
			<select id="workplaceactivity" name="work_place_activity_id" class="form-control" >
				<option value="none">---</option>
					@foreach ($workplaceactivities as $object)
						@if (\Illuminate\Support\Facades\Request::old('work_place_activity_id') == $object->id)
							<option id ='notdisabled' value="{{old('work_place_activity_id')}}" selected> {{ $object->activity }}</option>
						@else
							<option  value="{{ $object->id}}" object_id ="{{$object->work_place_id}}">
								{{ $object->activity }}
							</option>
						@endif
					@endforeach
			</select>		
	</div>
</div>	
<div class="form-group">
    <input size='50' name="note" type="text" class="form-control" value="{{ old('note') }}"  placeholder="Забележка...">
</div>
<div class="form-group action__btn">
    {!! Form::submit($formMode === 'edit' ? 'Редактирай' : 'Създай', ['class' => 'btn btn-primary']) !!}
</div>
</form>
<script>
	 //Set datepicker locale
    $.fn.datepicker.dates['bg'] = {
        days: ["Неделя", "Понеделник", "Вторник", "Сряда", "Четвъртък", "Петък", "Събота"],
        daysShort: ["Нед", "Пон", "Вто", "Сря", "Чет", "Пет", "Съб"],
        daysMin: ["Н", "П", "В", "С", "Ч", "П", "С"],
        months: ["Януари", "Февруари", "Март", "Април", "Май", "Юни", "Юли", "Август", "Септември", "Октомври", "Ноември", "Декември"],
        monthsShort: ["Ян", "Фев", "Мар", "Апр", "Май", "Юни", "Юли", "Авг", "Сеп", "Окт", "Ное", "Дек"],
        today: "днес",
        weekStart: 1,
        format: "yyyy-mm-dd"
    };
	$('#datetimepicker').datepicker({
        todayHighlight     : true,
        language           : 'bg',
        autoclose          : true,
        todayBtn           : true,
      
    });
	$(document).ready(function(){
		 var regionSelectedId = $("#regionSelect option:selected").val();
		if(regionSelectedId!='none') {
			$("#workplaceactivity option:selected").prop("selected", false)
			$('#workplaceactivity').prop('disabled', 'disabled');
			$("#workPlace > option").each(function() {
					$( "#workPlace option:selected" ).removeAttr("selected");
					$("#workPlace option:selected").prop("selected", false);
					if (regionSelectedId != jQuery(this).attr('region_id')) {
						jQuery(this).hide().prop('disabled', true);
					} else{
						//$(this).show();
						 jQuery(this).show().prop('disabled', false);
					}
			});
		}
		
	    $('#workplaceactivity').prop('disabled', 'disabled');
		$('#regionSelect').on('change', function () {
			var val = $(this).val();
			$("#workplaceactivity option:selected").prop("selected", false)
			$('#workplaceactivity').prop('disabled', 'disabled');
			$("#workPlace > option").each(function() {
					$( "#workPlace option:selected" ).removeAttr("selected");
					$("#workPlace option:selected").prop("selected", false);
					if (val != jQuery(this).attr('region_id')) {
						jQuery(this).hide().prop('disabled', true);
					} else{
						//$(this).show();
						 jQuery(this).show().prop('disabled', false);
					}
			});

		});
		
		$('#workPlace').on('change', function () {
			
			var val = $(this).val();
			$('#workplaceactivity').prop('disabled', false);
			$("#workplaceactivity > option").each(function() {
					$("#workplaceactivity option:selected").prop("selected", false)
					if (val != jQuery(this).attr('object_id')) {
						jQuery(this).hide().prop('disabled', true);
					} else{
						 jQuery(this).show().prop('disabled', false);
					}
			});

		});
		$('#workPlace').on('click', function () {
			
			var val = $(this).val();
			$('#workplaceactivity').prop('disabled', false);
			$("#workplaceactivity > option").each(function() {
					$("#workplaceactivity option:selected").prop("selected", false)
					if (val != jQuery(this).attr('object_id')) {
						jQuery(this).hide().prop('disabled', true);
					} else{
						 jQuery(this).prop("selected", true);
						 jQuery(this).show().prop('disabled', false);
					}
			});

		});
   })
</script>