@extends('layouts.backend')
<script type="text/javascript" src="https://code.jquery.com/jquery-1.11.3.min.js"></script>

<!-- Isolated Version of Bootstrap, not needed if your site already uses Bootstrap -->
<link rel="stylesheet" href="https://formden.com/static/cdn/bootstrap-iso.css" />

<!-- Bootstrap Date-Picker Plugin -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/js/bootstrap-datepicker.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/css/bootstrap-datepicker3.css"/>
@section('content')
    <div class="container-fluid">
        <div class="row">
           @include('service::sidebar')

             <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
                <div class="card">
                    <div class="card-header">Създай обект</div>
                    <div class="card-body common-form">
                        <a href="{{ url('/service/workplace') }}" title="Back">
                            <button class="btn btn-warning btn-md mb-3">
                                <i class="fa fa-arrow-left" aria-hidden="true"></i>
                                Назад
                            </button>
                        </a>
                        {!! Form::open(['url' => '/service/workplace', 'class' => 'form-horizontal']) !!}

                        @include ('service::workplace.form', ['formMode' => 'create'])

                        {!! Form::close() !!}

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
