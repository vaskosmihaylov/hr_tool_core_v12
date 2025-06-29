@extends('layouts.backend')

@section('content')
    <div class="container-fluid">
        <div class="row">
            @include('service::sidebar')

            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
                <div class="card">
                    <div class="card-header">Създаване на нов потребител</div>
                    <div class="card-body common-form">
                        <a href="{{ url('/service/users') }}" title="Назад">
                            <button class="btn btn-warning btn-md mb-3">
                                <i class="fa fa-arrow-left" aria-hidden="true"></i> 
                                Назад
                            </button>
                        </a>

                        @if ($errors->any())
                            <ul class="alert alert-danger">
                                @foreach ($errors->all() as $error)
									@if ($error == 'Полето E-mail вече съществува.')
										<li>Потребител с такъв имейл вече съществува.</li>
									@else
									   <li>{{ $error }}</li>
									@endif
                                @endforeach
                            </ul>
                        @endif

                        {!! Form::open(['url' => '/service/users/create', 'class' => 'form-horizontal']) !!}

                        @include ('service::users.form', ['formMode' => 'create'])

                        {!! Form::close() !!}

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
