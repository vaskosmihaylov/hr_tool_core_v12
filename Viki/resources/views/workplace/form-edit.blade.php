<form method="PATCH" action="{{route('service.workplace.update',$workplace->id)}}" enctype="multipart/form-data" id="edit-form">
<div class="form-row">
	<div class="col">
		<div class="form-group{{ $errors->has('name') ? ' has-error' : ''}}">
			{!! Form::label('name', 'Име: ', ['class' => 'control-label mb-2']) !!}
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
					value="{{ old('address',$workplace->address) }}" 
					id="address"
					maxlength="255"
					class="form-control"
				>
			</div>
			@if ($errors->has('address')) 
				<label class="error address-error-message" for="address">{{ $errors->first('address') }} </label>
			@endif
		</div>
	</div>
	</div>
<div class="form-row">
	<div class="col form-group">
		<label class="control-label mb-2"> Бюджет</label>
		<div class="input-field">
			<input
				type="decimal" 
				name="budget" 
				id="budget"
				min ="0"
				step=".01"
				pattern ='^\d*(\.\d{1,3})?$'
				max ="1000000"
				value="{{ old('budjet',$workplace->budget) }}" 
				class="form-control">
		</div>
	</div>
	<div class="col form-group">
		<label class="control-label mb-2">Бюджет валиден от :</label>
		<div class="input-field">
			<input
				type="text"
				name="budgetDate"
				id="budgetDate"
				autocomplete="off"
				required
				class="form-control"
				value="{{date('m-Y')}}"
			>
		</div>
	</div>
</div>
<div class="form-group">
	<label for="status" class="control-label mb-2">Статус</label>
	<select  name="status" class="form-control" style='width:60%!important'>
		@foreach ($statuses as $status)	
			<option value="{{ $status['id'] }}" @if($workplace->status == $status['id']) selected @endif>
			{{ $status['name'] }}
			</option>
		@endforeach
    </select>
</div>
<div class="form-row">
	<div class="col form-group">
		<label for="region" class="control-label mb-2">Регион</label>
		<select name="region" class="form-control" disabled>
				@foreach ($regions as $region)
					 <option value="{{ $region->id }}" @if($workplace->region_id == $region->id) selected @endif>
					 {{ $region->name }}
					 </option>
				@endforeach
		</select>
		@if ($errors->has('region'))
			<label class="text-danger" for="region"> {{ $errors->first('region') }} </label>
		@endif
	</div>
	<div class="col form-group">
		<label for="client" class="control-label mb-2">Клиент</label>
		<select  name="client" class="form-control" disabled>
				@foreach ($clients as $client)
					 <option value="{{ $client->id }}" @if($workplace->client_id == $client->id) selected @endif>
					 {{ $client->name }}
					 </option>
				@endforeach
		</select>
	</div>
</div>	
<div class="form-group action__btn">
    {!! Form::submit($formMode === 'edit' ? 'Редактирай' : 'Създай', ['class' => 'btn btn-primary']) !!}
</div>
</form>