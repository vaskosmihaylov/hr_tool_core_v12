@extends('layouts.backend')

@section('content')
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css" rel="stylesheet"/>
<div class="container-fluid">
    <div class="row">
        @include('service::sidebar')
         <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
            <div class="card">
                <div class="card-header">Изтриване работник за месец <b>{{$date}}</b> обект <b> {{$workplaceName}} </b></div>
                <div class="card-body common-form">
                    @if($errors->any())
                    <p class="text-danger">{{$errors->first()}}</p>
                    @endif
                    <?php $dateN = explode('-', $date); ?>
                    <a href="{{ url('service/presence/show', ['workPlaceId'=>$workPlaceId,'date'=>$dateN[0].'-'.$dateN[1]] ) }}" title="Back">
                        <button class="btn btn-warning btn-md mb-3">
                            <i class="fa fa-arrow-left" aria-hidden="true"></i>
                            Назад
                        </button>
                    </a>                      
                    <div class="form-row">
                        <table id='resultTable' class="table table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Име</th>
                                    <th>Фамилия</th>
                                    <th>ЕГН</th>                                  
                                    <th>Дейност</th>
                                    <th>Регион</th>	
                                    <th>Действие</th>	
                                </tr>
                            </thead>
                            <tbody>
                                @if(!empty($records))
                                @foreach($records as $value=>$it)
                                  <?php
                                     
                                    $activity = '';
                                    $activity = viki\Service\Models\Elequent\WorkPlaceActivity::find($value);
                                    if (!empty($activity)) {
                                      $activity = $activity->activity;
                                    }
                                    ?>
                                  @foreach($it as $items)
                                   @foreach($items as $item)
                                 
                                <tr>
                                    <td>{{$item->name}}</td>  
                                    <td>{{$item->family_name}}</td>
                                    <td>{{$item->egn}}</td>   
                                    <td>{{$activity}}</td> 
                                    <?php
                                    $region = '';
                                    $region = viki\Service\Models\Elequent\Region::find($item->region_id);
                                    if (!empty($region)) {
                                      $region = $region->name;
                                    }
                                    ?>
                                    <td>{{$region}}</td>
                                    <td>							
                                      <form method="POST" action="{{route('service.presence.remove.worker')}}"  name="ATForm2" 
                                            role="form2" enctype="multipart/form-data" id="edit-form2">
                                          {{ csrf_field() }}
                                          <input type='hidden' name='workPlaceId' value='{{$workPlaceId}}'/>
                                          <input type='hidden' name='date' value='{{$dateN[0].'-'.$dateN[1]}}'/>
                                          <input type='hidden' name='activityId' value='{{$value}}'/>
                                          <input type='hidden' name='workerId' value='{{$item->id}}'/>
                                          <div class="form-group action__btn">
                                              {!! Form::submit('Изтрий', ['class' => 'btn btn-danger']) !!}
                                          </div>
                                      </form>
                                    </td>
                                </tr>
                                 @endforeach
                                  @endforeach
                                @endforeach
                                @endif
                            </tbody>
                        </table> 
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endsection

