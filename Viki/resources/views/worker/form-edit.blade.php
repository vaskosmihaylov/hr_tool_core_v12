<form method="PATCH" action="{{route('service.worker.update',$worker->id)}}" enctype="multipart/form-data" id="edit-form">
@if($errors->any())
	<p class="text-danger">{{$errors->first()}}</p>
@endif
<script type="text/javascript" src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css" rel="stylesheet"/>
<div class="form-row">
	<div class="col">
		<div class="form-group{{ $errors->has('name') ? ' has-error' : ''}}">
			{!! Form::label('name', 'Име: ', ['class' => 'control-label mb-2']) !!}
			{!! Form::text('name', null, ['class' => 'form-control', 'required' => 'required']) !!}
			{!! $errors->first('name', '<p class="text-danger">:message</p>') !!}
		</div>
	</div>
	<div class="col">
		<div class="form-group{{ $errors->has('middle_name') ? ' has-error' : ''}}">
			{!! Form::label('middle_name', 'Презиме: ', ['class' => 'control-label mb-2']) !!}
			{!! Form::text('middle_name', null, ['class' => 'form-control', 'required' => 'required']) !!}
			{!! $errors->first('middle_name', '<p class="text-danger">:message</p>') !!}
		</div>
	</div>
	<div class="col">
		<div class="form-group{{ $errors->has('family_name') ? ' has-error' : ''}}">
			{!! Form::label('family_name', 'Фамилия: ', ['class' => 'control-label mb-2']) !!}
			{!! Form::text('family_name', null, ['class' => 'form-control', 'required' => 'required']) !!}
			{!! $errors->first('family_name', '<p class="text-danger">:message</p>') !!}
		</div>
	</div>
</div>
<div class="form-row">
	<div class="col">
		<label for="egn" class="control-label mb-2">ЕГН</label>
		<div class="form-group">
			<input
			type="text" 
			name="egn" 
			maxlength="10"
			minlength="10"
			required
			id="egn"
			pattern="^[0-9]+" 
			title="само цифри" 
			value="{{ old('egn', $worker->egn) }}" 
			class="form-control">
			{!! $errors->first('egn', '<p class="text-danger">:message</p>') !!}
		</div>
		@if ($errors->has('egn'))
			<label  class="text-danger" for="egn"> {{ $errors->first('egn') }} </label>
		@endif	
		
	</div>
	<div class="col">
			<label for="status" class="control-label mb-2">От дата</label>
			
				<input id="datepicker" name='start_date' type="text" class="form-control" placeholder="пример-YYY-M-D" 
					   required="" 
					   data-date-format="YYY-M-D"
					    value="{{ old('start_date',$worker->start_date) }}">
				<div class="input-group-addon">
					<span class="glyphicon glyphicon-th"></span>
				</div>
			
			@if ($errors->has('start_date'))
			<label  class="text-danger" for="start_date"> {{ $errors->first('start_daten') }} </label>
			@endif	
	</div>
</div>
<div class="form-row">
	<div class="col-4 form-group">
		<label for="status" class="control-label mb-2">Статус</label>
		<select  id='statusCH' name="status" class="form-control">
			@foreach ($statuses as $status)	
				<option value="{{ $status['id'] }}" @if($worker->status == $status['id']) selected @endif >
				{{ $status['name'] }}
				</option>
			@endforeach
		</select>
	</div>
	<div  id='unactive' class="col-4 form-group">
		<label for="status" class="control-label mb-2"> Неактивен от </label>
			<div class="input-group date" data-provide="datepicker" id="datetimepicker">
				<input name='unactive_from_date' type="text" class="form-control" @if (!empty($worker->unactive_from_date))  value="{{ $worker->unactive_from_date }}" @endif >
				<div class="input-group-addon">
					<span class="glyphicon glyphicon-th"></span>
				</div>
			</div>
			@if ($errors->has('unactive_from_date'))
			<label  class="text-danger" for="unactive_from_date"> {{ $errors->first('unactive_from_date') }} </label>
			@endif	
	</div>
</div>
<div class="form-row">
	<div class="col form-group">
		<label for="type_working" class="control-label mb-2">Работно време</label>
		<select  name="type_working" class="form-control">
			@foreach ($typesWork as $type)
				<option value="{{ $type['id'] }}" @if($worker->type_working == $type['id']) selected @endif >
				{{ $type['name'] }}
				</option>
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
				value="{{ old('hours_per_day',$worker->hours_per_day) }}" 
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
				value="{{ old('neto_salary',$worker->neto_salary) }}" 
				class="form-control">
		</div>
		@if ($errors->has('neto_salary'))
			<label class="text-danger" for="neto_salary"> {{ $errors->first('neto_salary') }} </label>
		@endif
	</div>
	 <div class="col form-group">
    	<label class="control-label mb-2">Осигурителен доход</label>
		<div class="input-field">
			<input
				type="decimal" 
				name="income" 
				id="income"
				min ="0"
				step=".01"
				pattern ='^\d*(\.\d{1,2})?$'
				max ="100000"
				value="{{ old('neto_salary',$worker->income) }}" 
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
		<select id='regionSelect' name="region_id" class="form-control" >
				@foreach ($regions as $region)
					 <option value="{{ $region->id }}"  @if($worker->region_id == $region->id) selected @endif>
					 {{ $region->name }}
					 </option>
				@endforeach
		</select>
		@if ($errors->has('region_id'))
			<label class="text-danger" for="region_id"> {{ $errors->first('region_id') }} </label>
		@endif
	</div>
	<div class="col form-group">
		<label for="work_place_id" class="control-label mb-2">Обект</label>
		<select id='workPlace' name="work_place_id" class="form-control" >
				<option  value='none'> -- Избери обект -- </option>
				@foreach ($workplaces as $object)
					 <option value="{{ $object->id }}" region_id ="{{$object->region_id}}" @if($worker->work_place_id == $object->id) selected @endif >
					 {{ $object->name }}
					 </option>
				@endforeach
		</select>
	</div>
	<div class="col form-group">
		<label for="work_place_activity_id" class="control-label mb-2">Дейност</label>
		<select id='workplaceactivity' name="work_place_activity_id" class="form-control" >
			<option value="none">-- Избери дейност --</option>
				@foreach ($workplaceactivities as $object)
					 <option value="{{ $object->id }}"   object_id ="{{$object->work_place_id}}" @if ((isset($workplaceactivity->id)) and ($workplaceactivity->id == $object->id)) selected @endif  >
						{{ $object->activity }}
					 </option>
				@endforeach
		</select>
	</div>
</div>	
<div class="form-group">
    <input size='50' name="note" type="text" class="form-control" value="{{ old('note',$worker->note) }}"  placeholder="Забележка...">
</div>
<div class="form-group action__btn">
    {!! Form::submit($formMode === 'edit' ? 'Редактирай' : 'Създай', ['class' => 'btn btn-primary']) !!}
</div>
</form>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js"></script>
<script src="http://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/bootstrap-datepicker.js"></script>
<script>
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
	   
		if ($("#statusCH").val() == 1 ) {
			$('#unactive').show();
		}else {
			$('#unactive').hide();
		}
		
		$('#statusCH').on('change', function () {
			$('#unactive').hide();
			var val = $(this).val();
			if (val == 1 ) {
				$('#unactive').show();
			} else{
				$('#unactive').hide();
			}
		});
		
		var regionSelectedId = $("#regionSelect option:selected").val();
		if(regionSelectedId!='none') {
			$("#workPlace > option").each(function() {
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
			$("#workplaceactivity option:selected").removeAttr("selected");
			$("#workplaceactivity option:selected").prop("selected", false);
			$("#workPlace option:selected").prop("selected", false);
			$('#workplaceactivity').prop('disabled', 'disabled');
			$("#workPlace").prepend("<option value='' selected='selected'>-- Избери обект --</option>");
			$("#workPlace > option").each(function() {
				$("#workplaceactivity option:selected" ).removeAttr("selected");
				//if($(this).val() == 'none') {
					//$(this).val().attr('selected','selected');
				//}
				
				if (val != jQuery(this).attr('region_id')) {
					$(this).hide().prop('disabled', true);
				} else{
					$(this).show().prop('disabled', false);
				}
				
			});

		});
		
		$('#workPlace').on('click', function () {
			var val = $(this).val();
			//alert(val);
			$("#workplaceactivity option:selected").removeAttr("selected");
			$('#workplaceactivity').prop('disabled', false);
			$('#workplaceactivity option')
					.filter(function() {
						return !this.value || $.trim(this.value).length == 0 || $.trim(this.text).length == 0;
					})
				   .remove();
		   
			$("#workplaceactivity > option").each(function() {
					
					if (val != jQuery(this).attr('object_id')) {
						$(this).hide().prop('disabled', true);
					} else{
						$(this).show().prop('disabled', false);
					}
			});
			$("#workplaceactivity").prepend("<option value='' selected='selected'>-- Избери дейност --</option>");
			
			
		});
		
		$('#workPlace').on('change', function () {
			var val = $(this).val();
			$('#workplaceactivity').prop('disabled', false);
			$("#workplaceactivity > option").each(function() {
					
					if (val != jQuery(this).attr('object_id')) {
						$(this).hide().prop('disabled', true);
					} else{
						$(this).show().prop('disabled', false);
					}
			});

		});
   })
</script>
  