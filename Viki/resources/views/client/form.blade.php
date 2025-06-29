<form method="POST" action="{{route('service.client.create')}}" enctype="multipart/form-data" id="edit-form">
@if($errors->any())
	<p class="text-danger">{{$errors->first()}}</p>
@endif
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
	<div class="col form-group">
		<label for="region" class="control-label mb-2">Регион</label>
		<select multiple="multiple" name="region[]" class="form-control">
				@foreach ($regions as $region)
					 <option value="{{ $region->id }}" >
					 {{ $region->name }}
					 </option>
				@endforeach
		</select>
		@if ($errors->has('region'))
			<label class="text-danger" for="region"> {{ $errors->first('region') }} </label>
		@endif
	</div>
	<div class="col form-group">
		<label class="control-label mb-2"> Бюджет</label>
		<div class="input-field">
			<input
				type="decimal" 
				name="budget" 
				required
				id="budget"
				min ="0"
				step=".01"
				pattern ='^\d*(\.\d{1,3})?$'
				max ="1000000"
				value="{{ old('budjet') }}" 
				title='само цифри'
				class="form-control">
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
<div class="form-group action__btn">
    {!! Form::submit($formMode === 'edit' ? 'Редактирай' : 'Създай', ['class' => 'btn btn-primary']) !!}
</div>
</form>
