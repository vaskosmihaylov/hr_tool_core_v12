<form method="POST" action="{{route('service.region.create')}}" enctype="multipart/form-data" id="edit-form">
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
