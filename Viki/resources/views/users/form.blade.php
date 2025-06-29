<script type="text/javascript" src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
<div class="form-group{{ $errors->has('name') ? ' has-error' : ''}}">
    {!! Form::label('name', 'Име: ', ['class' => 'control-label mb-2']) !!}
    {!! Form::text('name', null, ['class' => 'form-control', 'required' => 'required']) !!}
    {!! $errors->first('name', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group{{ $errors->has('email') ? ' has-error' : ''}}">
    {!! Form::label('email', 'Имейл: ', ['class' => 'control-label mb-2']) !!}
    {!! Form::email('email', null, ['class' => 'form-control', 'required' => 'required']) !!}
    {!! $errors->first('email', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group{{ $errors->has('password') ? ' has-error' : ''}}">
    {!! Form::label('password', 'Парола: ', ['class' => 'control-label mb-2']) !!}
    @php
        $passwordOptions = ['class' => 'form-control'];
        if ($formMode === 'create') {
            $passwordOptions = array_merge($passwordOptions, ['required' => 'required']);
        }
    @endphp
    {!! Form::password('password', $passwordOptions) !!}
    {!! $errors->first('password', '<p class="help-block">:message</p>') !!}
</div>

@if($disableSelect ?? false)
    <div class="form-group{{ $errors->has('roles') ? ' has-error' : ''}}">
        {!! Form::label('role', 'Роля: ', ['class' => 'control-label mb-2']) !!}
        {!! Form::select('roles[]', $roles, isset($user_roles) ? $user_roles : [], ['class' => 'form-control', 'multiple' => false, 'disabled']) !!}
        {!! Form::select('roles[]', $roles, isset($user_roles) ? $user_roles : [], ['class' => 'form-control', 'id' => 'roleSelect', 'multiple' => false, 'hidden']) !!}
    </div>
    <div class="form-group{{ $errors->has('regions') ? ' has-error' : ''}}">
        {!! Form::label('region', 'Регион: ', ['class' => 'control-label mb-2']) !!}
        {!! Form::select('regions[]', $regions, isset($user_regions) ? $user_regions : [], ['class' => 'form-control', 'multiple' => false, 'disabled']) !!}
        {!! Form::select('regions[]', $regions, isset($user_regions) ? $user_regions : [], ['class' => 'form-control', 'id' => 'regionSelect', 'multiple' => false, 'hidden']) !!}
    </div>
@else
    <div class="form-group{{ $errors->has('roles') ? ' has-error' : ''}}">
        {!! Form::label('role', 'Роля: ', ['class' => 'control-label mb-2']) !!}
        {!! Form::select('roles[]', $roles, isset($user_roles) ? $user_roles : [], ['class' => 'form-control', 'id' => 'roleSelect', 'multiple' => false]) !!}
    </div>
    <div class="form-group{{ $errors->has('regions') ? ' has-error' : ''}}">
        {!! Form::label('region', 'Регион: ', ['class' => 'control-label mb-2']) !!}
        {!! Form::select('regions[]', $regions, isset($user_regions) ? $user_regions : [], ['class' => 'form-control', 'id' => 'regionSelect', 'multiple' => true]) !!}
    </div>
@endif

    <div class="form-group{{ $errors->has('workPlaces') ? ' has-error' : ''}}" id='workPlaceSelectGroup' style="display: none;">
        {!! Form::label('workPlace', 'Обекти: ', ['class' => 'control-label mb-2']) !!}
        {!! Form::select('workPlaces[]', $workPlaces, isset($user_work_places) ? $user_work_places : [], ['class' => 'form-control', 'id' => 'workPlaceSelect', 'multiple' => true]) !!}
    </div>

<div class="form-group action__btn">
    {!! Form::submit($formMode === 'edit' ? 'Промяна' : 'Създаване', ['class' => 'btn btn-primary']) !!}
</div>
<script>
    $(document).ready(function () {

        if ($('#roleSelect').val() === 'supervisor') {
            $('#workPlaceSelectGroup').show();
        }

        $('#roleSelect').on('change', function () {
            if ($('#roleSelect').val() === 'supervisor') {
                $('#workPlaceSelectGroup').show();
            } else {
                $('#workPlaceSelectGroup').hide();
                $('#workPlaceSelect').val([]);
            }
        });

        $('optgroup').hide();
        $("optgroup[label='" + $('#regionSelect').val() + "']").show();
        $('#regionSelect').on('change', function (e) {
            let region = $(this).val();
            $('optgroup').hide();
            $('#workPlaceSelect').val([]);
            $("optgroup[label='" + region + "']").show();
        });
    });
</script>