@extends('layouts.backend')

@section('content')
    <div class="container-fluid">
        <div class="row">
           @include('service::sidebar')

             <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
                <div class="card">
                    <div class="card-header">Редактирай работник</div>
                    <div class="card-body common-form">
                        <a href="{{ url('/service/worker') }}" title="Back">
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

                        {!! Form::model($worker, [
                            'method' => 'PATCH',
                            'url' => ['service/worker/edit', $worker->id],
                            'class' => 'form-horizontal'
                        ]) !!}

                        @include ('service::worker.form-edit', ['formMode' => 'edit'])

                        {!! Form::close() !!}

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
