<form method="POST" action="{{route('service.workplace.create')}}" enctype="multipart/form-data" id="edit-form">
@if($errors->any())
	<h4 style="color:red">{{$errors->first()}}</h4>
@endif
<div class="form-row">
	<div class="col">
		<div class="form-group{{ $errors->has('name') ? ' has-error' : ''}}">
			{!! Form::label('name', 'Име: ', ['class' => 'control-label']) !!}
			{!! Form::text('name', null, ['class' => 'form-control', 'required' => 'required']) !!}
			{!! $errors->first('name', '<p class="text-danger">:message</p>') !!}
		</div>
	</div>
</div>
<div class="form-row">
	<div class="col">
		<div class="form-group">
			<label class="label-field bold mb-2">Адрес</label>
			<div class="input-field">
				<input 
					type="text" 
					name="address" 
					value="{{ old('address') }}" 
					id="address"
					maxlength="255"
					class="form-control"
				>
			</div>
			@if ($errors->has('address')) 
				<p class="text-danger">{{ $errors->first('address') }} </p>
			@endif
		</div>
	</div>
	<div class="col">
		<label class="control-label mb-2"> Бюджет</label>
		<div class="input-field">
			<input required=""
				type="decimal" 
				name="budget" 
				id="budget"
				min ="0"
				step=".01"
				pattern ='^\d*(\.\d{1,3})?$'
				max ="1000000"
				value="{{ old('budget') }}" 
				class="form-control"
			>
		</div>
		@if ($errors->has('budget'))
			<label class="text-danger" for="budget"> {{ $errors->first('budget') }} </label>
		@endif
	</div>
</div>
<div class="form-group">
	<label for="status" class="control-label mb-2">Статус</label>
	<select  name="status" class="form-control">
		@foreach ($statuses as $status)	
			<option value="{{ $status['id'] }}" >
			{{ $status['name'] }}
			</option>
		@endforeach
    </select>
</div>
<div class="form-row">
	<div class="col">
		<label for="region" class="control-label mb-2">Регион</label>
		<select id="regionSelect" name="region" class="form-control" required="">
			<option value='none'> ----</option>
			@foreach ($regions as $region)
				@if (\Illuminate\Support\Facades\Request::old('region') == $region->id)
					<option value="{{old('region')}}" selected >  {{ $region->name }}</option>
				@else
					<option value="{{ $region->id }}" >
					{{ $region->name }}
					</option>
				@endif
			 @endforeach
		</select>
		@if ($errors->has('region'))
			<label class="text-danger" for="region"> {{ $errors->first('region') }} </label>
		@endif
	</div>
	<div class="col form-group">
		<label for="client" class="control-label mb-2">Клиент</label>
		<select id='client' name="client" class="form-control" required="">	
			<option value='none'> ----</option>
			@foreach ($clients as $client)
				<?php
					$region_idsS = '';
					$region_idsA = array();
					foreach($client->regions as $regionC){
						$region_idsA[] = $regionC->id;
					}
					$region_idsS = implode(",", $region_idsA);
				?>
				@if (\Illuminate\Support\Facades\Request::old('client') == $client->id)
					<option  id='notdisabled' value="{{old('client')}}" selected  region_id='{{ $region_idsS }}'>  {{ $client->name }}</option>
				@else
					<option value="{{ $client->id }}"  region_id='{{ $region_idsS }}'>
					{{ $client->name }}
					</option>
				@endif
			@endforeach
		</select>
	</div>
</div>	
<div class="form-group action__btn">
    {!! Form::submit($formMode === 'edit' ? 'Редактирай' : 'Създай', ['class' => 'btn btn-primary']) !!}
</div>
</form>
<script>
   $(document).ready(function(){
		if($("#regionSelect").val() =='none') {
			$('#client').prop('disabled', 'disabled');
		}	
		$('#regionSelect').on('change', function () {
			var val = $(this).val();
			$('#client').prop('disabled', '');
			$("#client > option").each(function() {
					$("#client option:selected").prop("selected", false);
					const comma = ",";
					var reg = jQuery(this).attr('region_id');
					
					
					if (typeof reg !== "undefined") {
						
						if (reg.indexOf(",") != -1) {
							var reg = reg.split(",");
							console.log(reg)
							var n = reg.indexOf(val);
							if (n!=-1) {
								$(this).show().prop('disabled', false);
							} 
							else { 
								$(this).hide().prop('disabled', true);
							}
						} else {
							if (val != jQuery(this).attr('region_id')) {
								$(this).hide().prop('disabled', true);
							} else{
								$(this).show().prop('disabled', false);
							}
						}
					}
					
					
			});

		});
		
		
   })
</script>