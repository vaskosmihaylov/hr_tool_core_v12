<div class="form-group{{ $errors->has('name') ? ' has-error' : ''}}">
    {!! Form::label('value', 'Url: ', ['class' => 'control-label']) !!}
    {!! Form::text('value', null, ['class' => 'form-control', 'required' => 'required']) !!}
    {!! $errors->first('value', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group{{ $errors->has('type') ? ' has-error' : ''}}">
    {!! Form::label('type', 'Type: ', ['class' => 'control-label']) !!}
    {!! Form::select('type', array('1' => 'Relative', '2' => 'Absolute'), isset($resource) ? $resource->type : "", ['class' => 'form-control', 'multiple' => false]) !!}
    {!! $errors->first('type', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group{{ $errors->has('label') ? ' has-error' : ''}}">
    {!! Form::label('label', 'Permissions: ', ['class' => 'control-label']) !!}
    {!! Form::select('permissions', $permissions, isset($resource) ? $resource->permission->name : "", ['class' => 'form-control', 'multiple' => false]) !!}
    {!! $errors->first('label', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group">
    {!! Form::submit($formMode === 'edit' ? 'Update' : 'Create', ['class' => 'btn btn-primary']) !!}
</div>
