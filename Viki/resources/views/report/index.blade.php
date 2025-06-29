@extends('layouts.backend')
@section('content')
<style>
    /* Style the buttons inside the tab */
    .tab button {
        border:none;
        white-space: nowrap;

    }
    /* Change background color of buttons on hover */
    .tab button:hover {
        background-color: #ddd;
    }

    /* Create an active/current tablink class */
    .active {	
        color:green;
        background-color: #ddd;

    }
    select {
        height: 30px;
    }

    select[multiple] {
        height: auto;
    }
    .multiselect {
        width: 300px;
        height:150px;
        overflow-y: scroll;
    }

    .selectBox {
        position: relative;
    }

    .selectBox select {
        width: 100%;
        border:none;
        font-weight: bold;
    }

    .overSelect {
        position: absolute;
        left: 0;
        right: 0;
        top: 0;
        bottom: 0;
    }

    #checkboxes {
        display: block;

    }

    #checkboxes label {
        display: block;
    }

    #checkboxes label:hover {
        background-color: #1e90ff;
    }
</style>
<div class="container-fluid">
    <div class="row">
        @include('service::sidebar')
         <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
            <div class="card">
                <?php
                $containWorker = false;
                $containMonth = false;
                if (strpos($_SERVER['REQUEST_URI'], 'workers') !== false) {
                  $containWorker = true;
                }
                if (strpos($_SERVER['REQUEST_URI'], 'month_id') !== false) {
                  $containMonth = true;
                }
                ?>
                <div class="card-header">
                    <fieldset class='tab' id='tabs'>
                        <button class="tablinks"  onclick="showFormWorkers()"><a @if ($containWorker == false) class='active' @endif  href="{{ url('/service/reports') }}">Обща справка</a></button>|
                        @if((\Illuminate\Support\Facades\Auth::user()->hasRole('admin'))
                        || (\Illuminate\Support\Facades\Auth::user()->hasRole('Human Resource Management')))
                        <button class="tablinks"  onclick="showFormWorkers()"><a @if ($containWorker == true) class='active' @endif  href="{{ url('/service/reports/workers') }}">Работници по  месец</a></button>|
                        @endif
                        <button class="tablinks"><a  href="{{ url('/service/reports/workerplace') }}">Статус обекти </a>	</button>														
                    </fieldset>
                </div>
                @if($errors->any())
                <p class="text-danger">{{$errors->first()}}</p>
                @endif	
                <div class="card-body common-form reports">
                    @if($containWorker == true)
                    @if((\Illuminate\Support\Facades\Auth::user()->hasRole('admin'))
                    || (\Illuminate\Support\Facades\Auth::user()->hasRole('Human Resource Management')))
                    <span  id='Rabotniciheader'  style='display:none'>Справка работници по месец </span>&nbsp
                    <form action="/service/reports">
                    <?php $egnFromRequest = Request::get('egn'); ?>									 
                        <div class="table-filters">
                            <select name="month_id" class="form-control">
                                <option value="novalue">Избери месец</option>
                                @foreach ($months as $object)
                                <option value="{{ $object['id']}}" @if(($month_id == $object['id']) 
                                        and ($month_id!=null) and ($month_id !='novalue')) selected  @endif  >
                                        {{$object['name'] }}
                            </option>
                            @endforeach
                        </select>
                        <div>&nbsp;</div>
                        <select name="year_id" class="form-control">
                            @foreach ($years as $object)
                            <option value="{{ $object['id']}}" @if(($year_id == $object['id']) 
                                    and ($year_id!=null) and ($year_id !='novalue')) selected  @endif  test="{{$year_id}}">
                                    {{$object['id'] }}
                        </option>
                        @endforeach
                    </select>
                        <div>&nbsp;</div>
                    <input type='hidden'  name ='workers' value ='workers'/>
                    <input type="text"  class="form-control" name="egn" placeholder="Търси по ЕГН...">
                </div>
                <div class="input-group px-0 col-sm-6">
                    <span class="input-group-append">
                        <button class="btn btn-secondary search-btn" type="submit">
                            <i class="fa fa-search"></i>
                        </button>
                    </span>
                </div>

            </form>
            @endif
            @else
            {!! Form::open(['method' => 'GET', 'url' => '/service/reports', 'class' => 'form-inline my-2 my-lg-0 float-right', 'role' => 'search'])  !!}
            <div class="row" id='tableFilters'>
                <select name="month_id"  class="form-control">
                    <option value="novalue">Месец</option>
                    @foreach ($months as $object)
                    <option value="{{ $object['id']}}" @if(($month_id == $object['id']) 
                            and ($month_id!=null) and ($month_id !='novalue')) selected  @endif >
                            {{$object['name'] }}
                </option>
                @endforeach
            </select>
                <div>&nbsp;</div>
            <select name="year_id"  class="form-control">
                @foreach ($years as $object)
                <option value="{{ $object['id']}}" @if(($year_id == $object['id']) 
                        and ($year_id!=null) and ($year_id !='novalue')) selected  @endif  test="{{$year_id}}">
                        {{$object['id'] }}
            </option>
            @endforeach
        </select>
         <div>&nbsp;</div>
        @if (Auth::user()->hasRole('supervisor')) 
        @else
        <div class="multiselect form-control col-sm-3">
            <div class="selectBox"  onclick="removeAllRegions()">
                <select>
                    <option>Премахни</option>
                </select>
                <div class="overSelect"></div>
            </div>       
            <div id="checkboxes">           
                @foreach ($regions as $object)
                <label for="one">
                    <input type="checkbox"  class="Regions" name="region_id[]" onclick="checkOtherSelects(this)"
                          
                           client_id="{{$object->client_id}}" region_id ="{{$object->id}}" value="{{ $object['id']}}" />
                           {{$object['name'] }}</label>
                @endforeach
            </div>
        </div>
        @endif
        <div>&nbsp;</div>
        @if (Auth::user()->hasRole('supervisor')) 
         <div class="multiselect form-control col-sm-6">
        <div class="selectBox"  onclick="removeAllWorkplace()">
            <select>
                <option value="novalue" >Премахни обекти</option>
            </select>
            <div class="overSelect"></div>
        </div>       
        <div id="checkboxes">
            @foreach ($workplaces as $object)
            <label for="one">
                <input type="checkbox" name="workplace_id[]" class='Workplaces'
                       @if (in_array($object['id'], $workplace_id)) checked  @endif
                       client_id="{{$object->client_id}}" region_id ="{{$object->region_id}}" value="{{ $object['id']}}" />
                       {{$object['name'] }}</label>
            @endforeach
        </div>
    </div>
    @else
    <div class="multiselect form-control col-sm-3">
        <div class="selectBox"   onclick='removeAllClients()'>
            <select>
                <option>Премахни избраните клиенти </option>
            </select>
            <div class="overSelect"></div>
        </div>
        <div id="checkboxes" class='newclients'>
            @foreach ($clients as $object)
            <label for="one">
                <input type="checkbox"  class="Clients" name="client_id[]" id="Clients" onclick="checkOtherSelectsClients(this)"
                       value="{{ $object['id']}}" regions="{{$allRegionsForClient[$object['id']]}}"
                       @if (in_array($object['id'], $client_id)) checked  @endif />
                       {{ $object['name'] }}</label>
            @endforeach
        </div>
    </div>
     <div>&nbsp;</div>
    <div class="multiselect form-control col-sm-3">
        <div class="selectBox"  onclick="removeAllWorkplace()">
            <select>
                <option value="novalue" >Премахни избраните обекти</option>
            </select>
            <div class="overSelect"></div>
        </div>       
        <div id="checkboxes">
            @foreach ($workplaces as $object)
            <label for="one">
                <input type="checkbox" name="workplace_id[]" class='Workplaces'
                       @if (in_array($object['id'], $workplace_id)) checked  @endif
                       client_id="{{$object->client_id}}" region_id ="{{$object->region_id}}" value="{{ $object['id']}}" />
                       {{$object['name'] }}</label>
            @endforeach
        </div>
    </div>
</div>
@endif
<div class="row">&nbsp;</div>
<div class="input-group px-0 col-sm-6">
    <span class="input-group-append">
        <button class="btn btn-secondary search-btn" type="submit">
            <i class="fa fa-search"></i>
        </button>
    </span>
</div>
@endif
{!! Form::close() !!}
@if($containMonth == false)
@else
@if(count($workerRecorods)==0)
<span id='No-data' style='padding:15px,5px;color:red;'>Няма данни</span>
@else
<div class="table-filters export">             
    <?php
    if (!isset($client_id)) {
      $client_id = 'novalue';
    }
    $client_idD = '';
    $worker_idD = '';
    $workplace_idD = '';
    $region_idD = '';
    if (!empty($worker_id)) {
      $worker_idD = implode(",", $worker_id);
    }
    if (!empty($region_id)) {
      $region_idD = implode(",", $region_id);
    }
    if (!empty($client_id)) {
      $client_idD = implode(",", $client_id);
    }
    if (!empty($workplace_id)) {
      $workplace_idD = implode(",", $workplace_id);
    }
    ?>
    <div class="input-group col-5 px-0">
	@if (Auth::user()->hasRole('supervisor'))
		<form method="POST" action="{{route('service.exportExcel')}}" >
            <input type='hidden' name='yearD' value='{{$year_id}}'/>
            <input type='hidden' name='month_id' value='{{$month_id}}'/>
            <input type='hidden' name='workplace_idD' value='{{$workplace_idD}}'/>
            <input type='hidden' name='region_idD' value='{{$region_idD}}'/>
            <input type='hidden' name='client_idD' value='{{$client_idD}}'/>
            <input type='hidden' name='worker_idD' value='{{$worker_idD}}'/>
            {{ csrf_field() }}
            <div class="form-row">
                <div class="col-2" style='color: green'>
                    {!! Form::submit( 'Свали excel', ['class' => 'btn btn-success btn-md']) !!}
                </div>
            </div>
        </form>
    </div>
   <div>&nbsp;</div>
    @else
        <form method="POST" action="{{route('service.exportpdf')}}" >
            <input type='hidden' name='yearD' value='{{$year_id}}'/>
            <input type='hidden' name='month_id' value='{{$month_id}}'/>
            <input type='hidden' name='workplace_idD' value='{{$workplace_idD}}'/>
            <input type='hidden' name='region_idD' value='{{$region_idD}}'/>
            <input type='hidden' name='client_idD' value='{{$client_idD}}'/>
            <input type='hidden' name='worker_idD' value='{{$worker_idD}}'/>
            {{ csrf_field() }}
            <div class="form-row">
                <div class="col-2">
                    {!! Form::submit( 'Свали pdf', ['class' => 'btn btn-primary']) !!}
                </div>
            </div>
        </form>
        <div>&nbsp;</div>
        <form method="POST" action="{{route('service.exportExcel')}}" >
            <input type='hidden' name='yearD' value='{{$year_id}}'/>
            <input type='hidden' name='month_id' value='{{$month_id}}'/>
            <input type='hidden' name='workplace_idD' value='{{$workplace_idD}}'/>
            <input type='hidden' name='region_idD' value='{{$region_idD}}'/>
            <input type='hidden' name='client_idD' value='{{$client_idD}}'/>
            <input type='hidden' name='worker_idD' value='{{$worker_idD}}'/>
            {{ csrf_field() }}
            <div class="form-row">
                <div class="col-2" style='color: green'>
                    {!! Form::submit( 'Свали excel', ['class' => 'btn btn-success btn-md']) !!}
                </div>
            </div>
        </form>
    </div>
	@endif
</div>

<div class="table-responsive">
<?php $sumPerHourPerson = 0; ?>
    <table id='resultTable' class="table table-striped table-hover">
        <thead class="thead-dark">
            <tr>
                <th>Име</th>
                <th>Презиме</th>
                <th>Фамилия</th>
                <th>ЕГН</th>
                <th>Обект</th>
                <th>Клиент</th>
                <th>Регион</th>
<th>Изработени часове</th>				
				<th>Бонус</th>
                <th>Наказание</th>
                <th>Сума</th>
                <th>Сума + бонус - наказание</th>
            </tr>
        </thead>
        <tbody>
 <?php
            $sumTotalHours = 0;
            $sumTotal = 0;
            $bonusTotal = 0;
            $bonusPayCutTotal = 0;
             
            ?>
            @if(!empty($workerRecorods))
            @foreach($workerRecorods as $value=>$item)
            <tr>
                <td>{{$item->name}}</td>
                <td>{{$item->middle_name}}</td>
                <td>{{$item->family_name}}</td>
                <td>{{$item->egn}}</td>
                <td>{{$item->workPlaceName}}</td> 
                <?php
                $name = '';
                $name = viki\Service\Models\Elequent\Client::find($item->clId);
                if (!empty($name)) {
                  $name = $name->name;
                }
                ?>
                <td>{{$name}}</td>
                <?php
                $region = '';
                $region = viki\Service\Models\Elequent\Region::find($item->regId);
                if (!empty($region)) {
                  $region = $region->name;
                }
                ?>
                <td>{{$region}}</td>

                <?php $sumTotalHours = $sumTotalHours + $item->total; ?>
                <td>{{$item->total}}</td>
				  <?php  $bonus = viki\Service\Models\Elequent\WorkerBonus::getBonusCutByMonth($item->work_place_id, $item->worker_id, $item->selectedDate,viki\Service\Models\Elequent\WorkerBonus::BONUS);
                        $bonusTotal = $bonusTotal + $bonus;
                 ?>
                <td>{{$bonus}}</td>
                <?php  $paycut = viki\Service\Models\Elequent\WorkerBonus::getBonusCutByMonth($item->work_place_id, $item->worker_id, $item->selectedDate,viki\Service\Models\Elequent\WorkerBonus::PAY_CUT);
                       $bonusPayCutTotal = $bonusPayCutTotal + $paycut;
                ?>
                 <td>{{$paycut}}</td>
				<?php if (!empty($arraySum[$item->ID])) { ?>
                <td> {{round($arraySum[$item->ID] ,2)}} </td>
				<td> {{(round($arraySum[$item->ID] ,2)+ $bonus) - $paycut}} </td>
				<?php } ?>
<?php
$sumPerHour = 0;
if (!empty($arraySum)) {
  foreach ($arraySum as $key => $sum) {
    if ($item->ID == $key) {
      $sumPerHour = $arraySum[$item->ID];
    }
  }
}
?>
<?php $sumTotal = $sumTotal + round($sumPerHour, 2); ?>
                @endforeach
                @endif
            <tr><td>Общо:</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
				<td></td>
               <td>{{$bonusTotal}}</td>
                <td>{{$bonusPayCutTotal}}</td>
                <td>{{$sumTotal}}</td>
                <td>{{($sumTotal + $bonusTotal) - $bonusPayCutTotal}}</td>
            </tr>
        </tbody>
    </table>
<?php $params = \Illuminate\Support\Facades\Request::all(); ?>
    <div class="pagination"> {!! $workerRecorods->appends($params)->render(); !!} </div>
    @endif
    @endif
</div>

</div>
</div>
</div>
</div>
</div>
@endsection

<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
<script type='text/javascript'>

          $(document).ready(function () {
              $('#rabotnik').click(function (e) {
                  $(this).addClass('active');
              });
              var regionSelectedId = $("#regionSelect option:selected").val();
              if (regionSelectedId != 'none') {
                  $("#client > option").each(function () {
                      var stringReg = $(this).attr('regions');

                      if (stringReg.indexOf(',') > -1) {
                          var regions = stringReg.split(',');
                          //console.log(regions);
                          if (regions.includes(regionSelectedId)) {
                              $(this).show().prop('disabled', false);
                          } else {
                              $(this).hide().prop('disabled', true);
                          }
                      } else {
                          if (regionSelectedId != jQuery(this).attr('regions')) {
                              $(this).hide().prop('disabled', true);
                          } else {
                              $(this).show().prop('disabled', false);
                          }
                      }

                  });
                  $('#workPlace option')
                          .filter(function () {
                              return !this.value || $.trim(this.value).length == 0 || $.trim(this.text).length == 0;
                          })
                          .remove();

                  $("#workPlace").prepend("<option value='' selected='selected'>-- Избери обект --</option>");
                  $("#workPlace > option").each(function () {
                      //$("#workPlace option:selected" ).removeAttr("selected");
                      //$("#workPlace option:selected").prop("selected", false);
                      if (regionSelectedId != jQuery(this).attr('region_id')) {
                          jQuery(this).hide().prop('disabled', true);
                      } else {
                          //$(this).show();
                          jQuery(this).show().prop('disabled', false);
                      }
                  });
              }
              $('#workers1').change(function () {
                  if (!this.checked) {
                      //  ^
                      $('#tableFilters').fadeIn('slow');
                      $('#resultTable').fadeIn('slow');
                      $('#pdf').fadeIn('slow');
                      $('#excel').fadeIn('slow');
                      $('#No-data').fadeIn('slow');
                      document.getElementById("myDIV").style.display = "none";
                      //document.getElementById("#filtersRabotnici").style.display = "none";
                      $('#filtersRabotnici').fadeOut('slow');


                  } else {
                      $('#tableFilters').fadeOut('slow');
                      $('#resultTable').fadeOut('slow');
                      $('#pdf').fadeOut('slow');
                      $('#excel').fadeOut('slow');
                      $('#No-data').fadeOut('slow');
                      document.getElementById("myDIV").style.display = "inline";
                      $('#filtersRabotnici').fadeIn('slow');

                  }
              });


              $('#regionSelect').on('change', function () {
                  var val = $(this).val();
                  //clients
                  $('#client option')
                          .filter(function () {
                              return !this.value || $.trim(this.value).length == 0 || $.trim(this.text).length == 0;
                          })
                          .remove();
                  $("#client").prepend("<option value='' regions='' selected='selected'>-- Избери клиент --</option>");
                  $("#client > option").each(function () {
                      var stringReg = $(this).attr('regions');
                      //$("#client option:selected" ).removeAttr("selected");
                      //	$("#client  option:selected").prop("selected", false);

                      if (stringReg.indexOf(',') > -1) {
                          var regions = stringReg.split(',');
                          //console.log(regions);
                          if (regions.includes(val)) {
                              $(this).show().prop('disabled', false);
                          } else {
                              $(this).hide().prop('disabled', true);
                          }
                      } else {
                          if (val != jQuery(this).attr('regions')) {
                              $(this).hide().prop('disabled', true);
                          } else {
                              $(this).show().prop('disabled', false);
                          }
                      }

                  });
                  $('#workPlace option')
                          .filter(function () {
                              return !this.value || $.trim(this.value).length == 0 || $.trim(this.text).length == 0;
                          })
                          .remove();

                  $("#workPlace").prepend("<option value='' selected='selected'>-- Избери обект --</option>");

                  $("#workPlace > option").each(function () {

                      //$( "#workPlace option:selected" ).removeAttr("selected");
                      //$("#workPlace option:selected").prop("selected", false);
                      if (val != jQuery(this).attr('region_id')) {
                          $(this).hide().hide().prop('disabled', true);
                      } else {
                          $(this).show().show().prop('disabled', false);
                      }

                  });

              });

              $('#client').on('change', function () {
                  var val = $(this).val();
                  //clients
                  $('#workPlace option')
                          .filter(function () {
                              return !this.value || $.trim(this.value).length == 0 || $.trim(this.text).length == 0;
                          })
                          .remove();

                  $("#workPlace").prepend("<option value='' selected='selected'>-- Избери обект --</option>");
                  $("#workPlace > option").each(function () {
                      //$( "#workPlace option:selected" ).removeAttr("selected");
                      //$("#workPlace option:selected").prop("selected", false);
                      if (val != jQuery(this).attr('client_id')) {
                          $(this).hide().prop('disabled', true);
                      } else {
                          $(this).show().prop('disabled', false);
                      }

                  });

              });

          });
          function removeAllRegions() {
              $('.Regions').removeAttr('checked');
              $('.Clients').removeAttr('checked');
              $('.Workplaces').removeAttr('checked');
          }
          function removeAllClients() {
              $('.Clients').removeAttr('checked');
              $('.Workplaces').removeAttr('checked');
              $('.Regions').removeAttr('checked');
          }
          function removeAllWorkplace() {
              $('.Workplaces').removeAttr('checked');
          }
          
          function checkOtherSelects(checked){
           // console.log(checked);
            var myCheckboxes = new Array();
            $(".Regions:checked").each(function() {
              //console.log($(this).attr('region_id'));
              myCheckboxes.push($(this).attr('region_id'));
            });
            myCheckboxes = myCheckboxes.filter(function( element ) {
                  return element !== undefined;
            });
           // console.log(myCheckboxes);
            $('.Clients:checkbox').each(function() {
                var regions = jQuery(this).attr('regions');
               // console.log(regions);
                if (regions.indexOf(',') > -1) { 
                   var regionsArr = regions.split(','); 
                  for (let i = 0; i < myCheckboxes.length; i++) {
                    // console.log( myCheckboxes[i]);
                    // console.log(regionsArr);
                    if(jQuery.inArray(myCheckboxes[i], regionsArr) !== -1) {
                      console.log('daaaa');
                      $(this).prop('checked', true);
                      } else {
                       // console.log('neeee');
                     // $(this).prop('checked', false);
                    }
                     
                  };
                } else{
                  //console.log(regionsArr);
                  if(jQuery.inArray(regions, myCheckboxes) !== -1) {
                        $(this).prop('checked', true);
                    } else {
                        $(this).prop('checked', false);
                    }
                }  
            });
            $('.Workplaces:checkbox').each(function() {
                var regions = jQuery(this).attr('region_id');
                if(jQuery.inArray(regions, myCheckboxes) !== -1) {
                      $(this).prop('checked', true);
                  } else {
                      $(this).prop('checked', false);
                  }
            });
          }
          
          function checkOtherSelectsClients(checked){
            console.log(checked);
            var myCheckboxes = new Array();
            $(".Clients:checked").each(function() {
              //console.log($(this).attr('region_id'));
              myCheckboxes.push($(this).attr('regions'));
            });
            //console.log(myCheckboxes);
            myCheckboxes = myCheckboxes.filter(function( element ) {
                  return element !== undefined;
            });
            $('.Workplaces:checkbox').each(function() {
                var regions = jQuery(this).attr('region_id');
                if(jQuery.inArray(regions, myCheckboxes) !== -1) {
                      $(this).prop('checked', true);
                  } else {
                      $(this).prop('checked', false);
                  }
            });
          }
</script>