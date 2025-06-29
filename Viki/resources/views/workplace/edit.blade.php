@extends('layouts.backend')

@section('content')
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css" rel="stylesheet"/>
    <div class="container-fluid">
        <div class="row">
            @include('service::sidebar')
             <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
                <div class="card">
                    <div class="card-header">Редактирай обект</div>
                    <div class="card-body common-form">
                        <a href="{{ url('/service/workplace') }}" title="Back">
                            <button class="btn btn-warning btn-md mb-3">
                                <i class="fa fa-arrow-left" aria-hidden="true"></i> 
                                Назад
                            </button>
                        </a>

                        @if ($errors->any())
                            <ul class="alert alert-danger">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif

                        {!! Form::model($workplace, [
                            'method' => 'PATCH',
                            'url' => ['service/workplace/edit', $workplace->id],
                            'class' => 'form-horizontal'
                        ]) !!}

                        @include ('service::workplace.form-edit', ['formMode' => 'edit'])

                        {!! Form::close() !!}

                    </div>
                </div>
				<br>
                <div class="card">
                    <div class="card-header">История на бюджета</div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Валиден от дата:</th>
                                <th scope="col">Бюджет</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($budgets as $budget)
                                <tr>
                                    <td>{{date('m-Y', (int)$budget->valid_from)}}</td>
                                    <td>{{$budget->budget}}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js"></script>
<script src="http://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/bootstrap-datepicker.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/locales/bootstrap-datepicker.bg.min.js"></script>
<script>
	$(document).ready(function() {
		let asd = $('#budgetDate').datepicker({
			minViewMode: 1,
			language: 'bg',
			autoclose: true,
			format: "mm-yyyy",
		});
	});
</script>
