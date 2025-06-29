@extends('layouts.backend')

@section('content')
    <div class="container-fluid">
        <div class="row">
            @include('service::sidebar')

             <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
                <div class="card">
                    <div class="card-header">Промяна на потребител</div>
                    <div class="card-body common-form">
                        <a href="{{ url('/service/users') }}" title="Back">
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

                        {!! Form::model($user, [
                            'method' => 'PATCH',
                            'url' => ['/service/users/edit', $user->id],
                            'class' => 'form-horizontal'
                        ]) !!}

                        @include ('service::users.form', ['formMode' => 'edit'])

                        {!! Form::close() !!}

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
