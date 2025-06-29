<html>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
<style>
@media print {
    @page {
      margin: 0 0 0 0 !important;
    }
    body {
        height: 100%;
        width: 100%;
    }
    .table {
        transform: translate(8.5in, -100%) rotate(90deg);
        display: block;
        position: absolute;
    }
    table thead tr td {
       font-size: 26pt;
     }
}
</style>
<body>
	<table class="table" width="100%" border="1">
		<thead class="thead-dark">
			<tr>
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
					
					<th>{{$tableRow['workPlaceActivityName']}}</th>
					<th>{{$tableRow['workPlaceActivitySalary']}}</th>
					<th>-</th>
					<th>-</th>
					@for($i = 1; $i <= $monthDays; $i++)
						<td></td>
					@endfor
					<td style='background-color: {{$tableRow['workPlaceActivityUsedWorkingHours'] * $tableRow['workPlaceActivityHourPrice'] <= $tableRow['workPlaceActivityMaxBudget'] ? "PaleGreen" : "Tomato"}}' id="budgetGraphForId-{{$tableRow['workPlaceActivityId']}}">
						<span id="totalBudgetUsedForId-{{$tableRow['workPlaceActivityId']}}">{{ round($tableRow['workPlaceActivityUsedWorkingHours'] * $tableRow['workPlaceActivityHourPrice'] , 2)}}</span>/<span id="totalBudgetForId-{{$tableRow['workPlaceActivityId']}}">{{$tableRow['workPlaceActivityMaxBudget']}}</span>
					</td>
					<td style='background-color: {{$tableRow['workPlaceActivityUsedWorkingHours'] <= $tableRow['workPlaceActivityMaxWorkingHours'] ? "PaleGreen" : "Tomato"}} ' id="hoursGraphForId-{{$tableRow['workPlaceActivityId']}}">
						<span id="totalWorkingUsedHoursForId-{{$tableRow['workPlaceActivityId']}}">{{$tableRow['workPlaceActivityUsedWorkingHours']}}</span>/<span id="totalWorkingHoursForId-{{$tableRow['workPlaceActivityId']}}">{{$tableRow['workPlaceActivityMaxWorkingHours']}}</span>
					</td>
				</tr>
				@foreach($tableRow['workPlaceActivityWorkers'] as $workPlaceActivityWorker)
					<tr>
						<td>-</td>
						<td></td>
						<td>{{$workPlaceActivityWorker->name}}</td>
						<td>{{$workPlaceActivityWorker->family_name}}</td>

						@php($allHours = 0)

						@for($i = 1; $i <= $monthDays; $i++)

							@php($userDateHours = 0)
							@php($unApprovedClass = '')

							@if($workerRecord = $workPlaceActivityWorker->workerRecords()
									->where('date', date_format(date_create_from_format('d-m-Y', $i . '-' . $selectedDate), 'Y-m-d'))
									->where('work_place_activity_id', $tableRow['workPlaceActivityId'])
									->first())
								@php($allHours += $workerRecord->hours)
								@php($userDateHours = floatval($workerRecord->hours))
								@php($unApprovedClass = $workerRecord->approvement_status == 0 ? "unapproved":"")
							@endif

							@php($vacationType = false)
							@if($workPlaceActivityWorker->vacations->count())
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
</body>
</html>