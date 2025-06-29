@extends('layouts.backend', [ 'type' => 'presence' ])

@section('content')
    <div class="container-fluid">
        <div class="row">
             @include('service::sidebar')
            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 presence-content">
                @if($selectedWorkPlace)
                    @if($tableData)
                        <div class="presence-content__select">
                            <div class="select col-lg-5 col-sm-9 form-group{{ $errors->has('userWorkPlaces') ? ' has-error' : ''}}" id='userWorkPlaces'>
                                {!! Form::label('userWorkPlaces', 'Обект: ', ['class' => 'control-label']) !!}
                                {!! Form::select('userWorkPlaces[]', $userWorkPlaces, isset($workPlaceId) ? $workPlaceId : [], ['class' => 'form-control work-place-and-date-control', 'id' => 'userWorkPlacesSelect', 'multiple' => false]) !!}
                            </div>
                            <div class="select col-lg-5 col-sm-9 form-group{{ $errors->has('availableMonths') ? ' has-error' : ''}}" id='availableMonths'>
                                {!! Form::label('availableMonths', 'Месец: ', ['class' => 'control-label']) !!}
                                {!! Form::select('availableMonths[]', $availableMonths, isset($selectedDate) ? $selectedDate : [], ['class' => 'form-control work-place-and-date-control', 'id' => 'availableMonthsSelect', 'multiple' => false]) !!}
                            </div>
                        </div>
                        <div class="card" id="datatableWrapper" style="opacity: 0.0">
                            <div class="card-header">Архив на присъствена форма за <strong><span id='today'></span></strong> месец. <span>Бюджет на обекта : <strong><span id="totalUsedBudget">{{$tableData[array_key_first($tableData)]['workPlaceTotalUsedBudget']}}</span> / {{$tableData[array_key_first($tableData)]['workPlaceBudget']}} лв</strong></span> </div>
                            <div class="card-body col-lg-12 presence-table">
                                <table id="presence" class="table table-bordered stripe">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>ИД</th>
                                            <th>Длъжност</th>
                                            <th>Заплата</th>
                                            <th>Име</th>
                                            <th>Фамилия</th>
                                            @for($i = 1; $i <= $monthDays; $i++)

                                                @if(in_array($i, $weekDays))
                                                    <th class="day-off">{{$i}}</th>
                                                @else
                                                    <th>{{$i}}</th>
                                                @endif
                                            @endfor
                                            <th>Цена</th>
                                            <th>Общо</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($tableData as $tableRow)
                                            <tr class="profession">
                                                <th></th>
                                                <th>{{$tableRow['workPlaceActivityName']}}</th>
                                                <th>{{$tableRow['workPlaceActivitySalary']}}</th>
                                                <th>-</th>
                                                <th>-</th>
                                                @for($i = 1; $i <= $monthDays; $i++)
                                                    <td></td>
                                                @endfor
                                                <td class="price" style='background-color: {{$tableRow['workPlaceActivityUsedWorkingHours'] * $tableRow['workPlaceActivityHourPrice'] <= $tableRow['workPlaceActivityMaxBudget'] ? "PaleGreen" : "Tomato"}}' id="budgetGraphForId-{{$tableRow['workPlaceActivityId']}}">
                                                    <span class="activityTotalPrice" id="totalBudgetUsedForId-{{$tableRow['workPlaceActivityId']}}">{{ round($tableRow['workPlaceActivityUsedWorkingHours'] * $tableRow['workPlaceActivityHourPrice'] , 2)}}</span>/<span id="totalBudgetForId-{{$tableRow['workPlaceActivityId']}}">{{$tableRow['workPlaceActivityMaxBudget']}}</span>
                                                </td>
                                                <td style='background-color: {{$tableRow['workPlaceActivityUsedWorkingHours'] <= $tableRow['workPlaceActivityMaxWorkingHours'] ? "PaleGreen" : "Tomato"}} ' id="hoursGraphForId-{{$tableRow['workPlaceActivityId']}}">
                                                    <span id="totalWorkingUsedHoursForId-{{$tableRow['workPlaceActivityId']}}">{{$tableRow['workPlaceActivityUsedWorkingHours']}}</span>/<span id="totalWorkingHoursForId-{{$tableRow['workPlaceActivityId']}}">{{$tableRow['workPlaceActivityMaxWorkingHours']}}</span>
                                                </td>
                                            </tr>
                                            @foreach($tableRow['workPlaceActivityWorkers'] as $workPlaceActivityWorker)
                                                <tr>
                                                    <td>{{$workPlaceActivityWorker->id}}</td>
                                                    <td>-</td>
                                                    <td></td>
                                                    <td>{{$workPlaceActivityWorker->name}}</td>
                                                    <td>{{$workPlaceActivityWorker->family_name}}</td>

                                                    @php($allHours = 0)

                                                    @for($i = 1; $i <= $monthDays; $i++)

                                                        @php($userDateHours = 0)
                                                        @php($unApprovedClass = '')

                                                        @foreach($workPlaceActivityWorker->worker_records as $workerRecord)
                                                            @if($workerRecord->date == date_format(date_create_from_format('d-m-Y', $i . '-' . $selectedDate), 'Y-m-d') && $workerRecord->work_place_activity_id == $tableRow['workPlaceActivityId'])
                                                                @php($allHours += $workerRecord->hours)
                                                                @php($userDateHours = floatval($workerRecord->hours))
                                                                @php($unApprovedClass = $workerRecord->status == 0 ? "unapproved":"")
                                                            @endif
                                                        @endforeach

                                                        @php($vacationType = false)
                                                        @if(count($workPlaceActivityWorker->vacations) > 0)
                                                            @foreach($workPlaceActivityWorker->vacations as $vacation)
                                                                @if(date_format(date_create_from_format('d-m-Y', $i . '-' . $selectedDate), 'Y-m-d') >= $vacation->start_date && date_format(date_create_from_format('d-m-Y', $i . '-' . $selectedDate), 'Y-m-d') <= $vacation->end_date)
                                                                    @php($vacationType = $vacation->type)
                                                                @endif
                                                            @endforeach
                                                        @endif

                                                        <td data-workerWorkPlaceActivityId="{{$tableRow['workPlaceActivityId']}}"
                                                            data-workerId="{{$workPlaceActivityWorker->id}}"
                                                            data-dateDay="{{$i}}"
                                                            data-initial-value="{{$userDateHours}}"
                                                            data-work-place-activity-hour-price="{{$tableRow['workPlaceActivityHourPrice']}}"
                                                            data-work-place-budget="{{$tableRow['workPlaceBudget']}}"
                                                            unselectable="on"
                                                            contenteditable="false"
                                                            class="noselect {{$vacationType ? 'vacation' : ""}}  {{$unApprovedClass}} rows-activity-id-{{$tableRow['workPlaceActivityId']}} row-worker-activity-{{$workPlaceActivityWorker->id}}-{{$tableRow['workPlaceActivityId']}} workerHoursData">{{$userDateHours}}</td>
                                                    @endfor
                                                    <td id="price-for-{{$tableRow['workPlaceActivityId']}}-{{$workPlaceActivityWorker->id}}">{{round($allHours * $tableRow['workPlaceActivityHourPrice'], 2)}}</td>
                                                    <td id="total-hours-for-{{$tableRow['workPlaceActivityId']}}-{{$workPlaceActivityWorker->id}}">{{$allHours}}</td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="presence-content__buttons">
                            <a href="{{ url('service/presence/config',['workPlaceId'=>$selectedWorkPlace->id,'date'=>$selectedDate]) }}" class="btn btn-success btn-md"><i class="fa fa-plus" aria-hidden="true"></i> Конфигурирай месеца</a>
                        </div>
                        <div class="card row">
                            <div class="card-header">Моля конфигурирайте обект <strong>{{$selectedWorkPlace->name}}</strong> за <strong><span id='today'>{{$selectedDate}}</span></strong>!</div>
                        </div>
                    @endif
                @else
                    <div class="card row">
                        <div class="card-header">Все още нямате обект във вашият регион !</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.21/af-2.3.5/b-1.6.2/b-colvis-1.6.2/b-flash-1.6.2/b-html5-1.6.2/b-print-1.6.2/cr-1.5.2/fc-3.3.1/fh-3.1.7/kt-2.5.2/r-2.2.4/rg-1.1.2/rr-1.2.7/sc-2.0.2/sp-1.1.0/sl-1.3.1/datatables.min.css"/>
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css">

<script
        src="https://code.jquery.com/jquery-3.5.1.min.js"
        integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0="
        crossorigin="anonymous"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js" defer></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js" defer></script>
<script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.21/af-2.3.5/b-1.6.2/b-colvis-1.6.2/b-flash-1.6.2/b-html5-1.6.2/b-print-1.6.2/cr-1.5.2/fc-3.3.1/kt-2.5.2/r-2.2.4/rg-1.1.2/rr-1.2.7/sc-2.0.2/sp-1.1.0/sl-1.3.1/datatables.min.js" defer></script>

<script>

    var tableNotEditable = '{{$tableNotEditable}}';
    // fix selects on page back or forward.
    window.onpageshow = function() {
        $('select').each(function () {
            var select = $(this);
            var selectedValue = select.find('option[selected]').val();

            if (selectedValue) {
                select.val(selectedValue);
            } else {
                select.prop('selectedIndex', 0);
            }
        });
    };

    $(document).ready(function() {

        if($('#presence').length != 0) {

            $('#availableMonthsSelect').change(function() {

                var url = "{{url('service/archive')}}";

                url = url + '/' + $('#userWorkPlacesSelect').val() + '/' + $('#availableMonthsSelect').val();

                window.location.href = url
            });

            $('#userWorkPlacesSelect').change(function() {

                var url = "{{url('service/archive')}}";

                url = url + '/' + $('#userWorkPlacesSelect').val();

                window.location.href = url
            });

            const monthNames = ["януари", "февруари", "март", "април", "май", "юни",
                "юли", "август", "септември", "октомври", "ноември", "декември"
            ];

            document.getElementById("today").innerHTML= monthNames['{{ (int)ltrim(strtok($selectedDate, '-'), "0") - 1 }}'];

            var leftColumns;
            var rightColumns;
            var tablet = window.matchMedia("(max-width: 1024px)");
            var mobile = window.matchMedia("(max-width: 768px)");
            var small = window.matchMedia("(max-width: 550px)");

            var responsive = function() {
                if (small.matches) {
                    rightColumns = 0;
                    leftColumns = 2;

                } else if (mobile.matches) {
                    rightColumns = 1;
                    leftColumns = 2;

                } else if (tablet.matches) {
                    rightColumns = 2;
                    leftColumns = 3;

                } else {
                    rightColumns = 2;
                    leftColumns = 5;
                }
            };
            responsive();
			 var customButtons = [
                {
                    extend: 'excel',
                    text: 'Експорт',
                    className: "btn btn-info",
                    init: function(api, node, config) {
                        $(node).removeClass('dt-button')
                    },
                    exportOptions: {
                        format: {
                            body: function ( data, row, column, node ) {



                                if (data.includes('span') && $(data).is('span')) {
                                    return $(data).text();
                                } else if ($(node).hasClass('vacation1')) {
                                    return data.concat(' (ПО)');
                                }
                                else if ($(node).hasClass('vacation2')) {
                                    return data.concat(' (НО)');
                                }
                                else if ($(node).hasClass('vacation3')) {
                                    return data.concat(' (Б)');
                                }
                                else {
                                    return data;
                                }
                            }
                        }
                    }
                }
               
            ];

            var table = $('#presence').DataTable( {
                scrollY:        "650px",
                bSort:           false,
                scrollCollapse:  true,
                paging:          false,
                select:          true,
                sScrollX:       "100%",
                bScrollCollapse: true,
				language: {
				  'search' : 'Търси:' /*Empty to remove the label*/
				},
                fixedColumns:   {
                    leftColumns: leftColumns,
                    rightColumns: rightColumns
                },
                initComplete: function( settings, json ) {
                    $('#datatableWrapper').fadeTo(500,1)
                },
                dom: 'ftpB',
                "fixedHeader": {
                    "header": true
                },
                "columnDefs": [
                    {
                        "targets": [ 0 ],
                        "visible": false,
                        "searchable": false
                    },
                ],
                buttons: customButtons
            } );

            var weekDays = JSON.parse("{{ json_encode($weekDays) }}");

            weekDays.forEach(function (weekDay) {
                table.rows().every(function (rowIdx, tableLoop, rowLoop) {
                    var cell = table.cell({ row: rowIdx, column: weekDay + 4 }).node();
                    $(cell).addClass('day-off');
                });
            });
        }
    });


</script>
<style>
    .noselect {
        -webkit-touch-callout: none; /* iOS Safari */
        -webkit-user-select: none; /* Safari */
        -khtml-user-select: none; /* Konqueror HTML */
        -moz-user-select: none; /* Old versions of Firefox */
        -ms-user-select: none; /* Internet Explorer/Edge */
        user-select: none; /* Non-prefixed version, currently
                                  supported by Chrome, Edge, Opera and Firefox */
    }
    table{
        margin: 0 auto;
        clear: both;
        border-collapse: collapse;
        table-layout: fixed;
        word-wrap:break-word;
    }
    div.presence-table div.DTFC_RightBodyLiner,
    div.presence-table div.DTFC_LeftBodyLiner {
        overflow: hidden;
    }
    button.export_excel,
    button.ctrl_btn {
        padding: .5rem 4rem;
        background: #9fc5f8;
        border: none;
        box-shadow: 4px 4px 1px -2px black;
        border: 2px solid black;
        border-radius: 0;
        transition: .3s;
    }
    button.ctrl_btn {
        background: #e06566;
        padding: .5rem;
    }
    button.dt-button.export_excel:hover:not(.disabled),
    div.dt-button.export_excel:hover:not(.disabled),
    a.dt-button.export_excel:hover:not(.disabled) {
        background: #5e9ef5;
        border: 2px solid black;
        transform: scale(1.05);
    }
    button.dt-button.ctrl_btn:hover:not(.disabled),
    div.dt-button.ctrl_btn:hover:not(.disabled),
    a.dt-button.ctrl_btn:hover:not(.disabled) {
        background: #e83e40;
        border: 2px solid black;
        transform: scale(1.05);
    }
    .presence-table table th,
    .presence-table table td {
        text-align: center;
    }
    .presence-table div.dt-buttons {
        float: right !important;
        margin-top: 10px;
    }
    .presence-table .dataTables_wrapper .dataTables_paginate {
        float: left !important;
    }
    table .profession th:first-child {
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
    }
    table .profession th:hover {
        white-space: normal;
    }
    .presence-table table tr td.day-off,
    .presence-table table tr th.day-off {
        background: #e06566;
        color: white;
    }
    .presence-table table tr td.unsaved-changes,
    .presence-table table tr th.unsaved-changes {
        background: yellow;
        color: black;
    }

    .presence-table table tr td.unapproved,
    .presence-table table tr th.unapproved {
        background: dimgrey;
        color: white;
    }

    .presence-table table tr td.vacation,
    .presence-table table tr th.vacation {
        background-color: skyblue;
        color: black;
    }

    .presence-table table tr td.vacation.day-off,
    .presence-table table tr th.vacation.day-off {
        background-color: hotpink;
        color: black;
    }
</style>