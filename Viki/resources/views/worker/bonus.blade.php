@extends('layouts.backend')

@section('content')
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css" rel="stylesheet"/>
    <div class="container-fluid">
        <div class="row">
           @include('service::sidebar')

            <div class="col-md-10 table-content">
                <div class="card">
                    <div class="card-header">Бонус/Наказание работник</div>
                    <div class="card-body common-form">
                        @if ($message = Session::get('success'))
                            <div class="alert alert-success alert-block">
                                <button type="button" class="close" data-dismiss="alert">×</button>
                                <strong>{{ $message }}</strong>
                            </div>
                        @endif
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
                        <br>
                        <p>Бонус/Наказание за Работник : <strong>{{$worker->name}} {{$worker->middle_name}} {{$worker->family_name}}</strong></p>
                        <br>
                        <br>
                        {!! Form::model($worker, [
                            'method' => 'PATCH',
                            'url' => ['service/worker/bonus', $worker->id],
                            'class' => 'form-horizontal'
                        ]) !!}

                        @include ('service::worker.form-bonus')

                        {!! Form::close() !!}

                    </div>
                </div>
                <br>
                @if($worker->bonus()->exists())
                    <div class="card">
                        <div class="card-header">История на Бонус/наказание за {{$worker->name}} {{$worker->middle_name}} {{$worker->family_name}}</div>
                        <table class="table" style="margin-bottom: 0px">
                            <thead>
                                <tr>
                                    <th scope="col">Тип</th>
                                    <th scope="col">Обект</th>
                                    <th scope="col">Месец</th>
                                    <th scope="col">Сума</th>
                                    <th scope="col">Статус на одобрение при бонус</th>
                                    <th scope="col">Опции</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($worker->bonus as $bonus)
                                    <tr>
                                        <td>{{$bonus->type == 0 ? 'Бонус' : 'Наказание'}}</td>
                                        <td>{{$bonus->workplace->name}}</td>
                                        <?php  $dates = explode('-', $bonus->for_month);?>
                                        <td>{{$dates[0] . "-" . $dates[1]}}</td>
                                        <td>{{$bonus->sum}}</td>
                                        <?php   $approvementbonus = '-';
                                                $approvementbonus = viki\Service\Models\Elequent\Approvement::getApprovementBonus($bonus->id);
                                        ?>
                                        <td>{{$approvementbonus}}</td>
                                        <td><?php if($bonus->type == 1){?>
                                          {!! Form::open([
                                        
                                                'method' => 'DELETE',
                                                'url' => ['/service/worker/bonus/delete', $bonus->id],
                                                'style' => 'display:inline'
                                            ]) !!}
                                            {!! Form::button('<i class="fa fa-trash-o" aria-hidden="true"></i>', array(
                                                    'type' => 'submit',
                                                    'class' => 'btn btn-danger btn-sm',
                                                    'title' => 'Изтрий',
                                                    'onclick'=>'return confirm("Сигурни ли сте?")'
                                            )) !!}
                                            {!! Form::close() !!}
                                        <?Php }?></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js"></script>
    <script src="http://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/bootstrap-datepicker.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/locales/bootstrap-datepicker.bg.min.js"></script>

    <script>
        $(document).ready(function() {
            let asd = $('#bonusDate').datepicker({
                minViewMode: 1,
                language: 'bg',
                autoclose: true,
                format: "mm-yyyy",
            });
        });
    </script>
@endsection
