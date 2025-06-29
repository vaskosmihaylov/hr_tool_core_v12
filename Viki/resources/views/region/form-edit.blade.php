<form method="PATCH" action="{{route('service.region.update',$region->id)}}" enctype="multipart/form-data" id="edit-form">
@if($errors->any())
	<p class="text-danger">{{$errors->first()}}</p>
@endif
<div class="form-row col-8 px-0">
	<div class="col-12 pr-0 form-group{{ $errors->has('name') ? ' has-error' : ''}}">
		{!! Form::label('name', 'Име: ', ['class' => 'control-label mb-2']) !!}
		{!! Form::text('name', null, ['class' => 'form-control', 'required' => 'required']) !!}
		{!! $errors->first('name', '<p class="text-danger">:message</p>') !!}
	</div>
</div>
<div class="form-group col-8 px-0">
	<label for="status" class="control-label mb-2">Статус</label>
	<select  name="status" class="form-control">
		@foreach ($statuses as $status)	
			<option value="{{ $status['id'] }}" @if($region->status == $status['id']) selected @endif>
			{{ $status['name'] }}
			</option>
		@endforeach
    </select>
</div>
<div class="form-group action__btn">
    {!! Form::submit($formMode === 'edit' ? 'Редактирай' : 'Създай', ['class' => 'btn btn-primary']) !!}
</div>
</form>