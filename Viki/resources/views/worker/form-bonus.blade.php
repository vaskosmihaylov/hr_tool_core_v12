<form method="PATCH" action="{{route('service.workplace.update',$worker->id)}}" enctype="multipart/form-data" id="edit-form">

    <div class="form-row">
        <div class="col-2 form-group">
            <input type="hidden" name="workerId" value="{{$worker->id}}">
            <label for="type" class="control-label mb-2">Тип</label>
            <select name="type" class="form-control">
                @foreach ($bonusTypes as $key => $type)
                    <option value="{{ $key }}">
                        {{ $type }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-3 form-group">
            <label for="workplace" class="control-label mb-2">Обект</label>
            <select name="workplaceId" class="form-control">
                @foreach ($workplaces as $key=>$workplace)
                    <option value="{{ $key }}">
                        {{ $workplace }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-1 form-group">
            <label class="control-label mb-2">Месец</label>
            <div class="input-field">
                <input
                        type="text"
                        name="bonusDate"
                        id="bonusDate"
                        autocomplete="off"
                        required
                        class="form-control"
                        value="{{date('m-Y')}}"
                >
            </div>
        </div>
        <div class="col-1 form-group">
            <label class="control-label mb-2">Сума</label>
            <div class="input-field">
                <input
                        type="decimal"
                        name="bonusValue"
                        id="bonusValue"
                        min ="0"
                        step=".01"
                        pattern ='^\d*(\.\d{1,3})?$'
                        max ="1000000"
                        required
                        class="form-control">
            </div>
        </div>
    </div>
    <div class="form-row">


    </div>

    <div class="form-group action__btn">
        {!! Form::submit('Създай', ['class' => 'btn btn-primary']) !!}
    </div>
</form>