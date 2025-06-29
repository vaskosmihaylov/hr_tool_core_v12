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
					<div class="card-body common-form">
						@if(empty($workerRecorods))
						<span>Няма данни</span>
						@else
						<div class="table-responsive">
						
                          <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Име</th>
										<th>Презиме</th>
										<th>Фамилия</th>
                                        <th>ЕГН</th>
										<th>Отпуска</th>
                                        <th>Изработени часове</th>
                                        <th>Сума</th>
                                    </tr>
                                </thead>
                                <tbody>
									@if(!empty($workerRecorods))
										@foreach($workerRecorods as $value=>$item)
											<tr>
												<td>{{$item->name}}</td>
												<td>{{$item->middle_name}}</td>
												<td>{{$item->family_name}}</td>
												<td>{{$item->egn}}</td>
												<?php $vacation = array();
												if($item->selectedDate) {
													$selectDate = $item->selectedDate;
														$query = viki\Service\Models\Elequent\Vacation::where('worker_id', '=', $item->worker_id)->where(function($q) use ($selectDate,$lastDayOfMonth) {
													  $q->where(function ($q) use($selectDate,$lastDayOfMonth) {
														$q->where('end_date', '>=', $selectDate);
														$q->where('start_date', '<=', $selectDate);
													  });
													  $q->orWhere(function ($q) use($selectDate,$lastDayOfMonth) {
														  $q->where('start_date', '>=', $selectDate);
														  $q->where('end_date', '<=', $lastDayOfMonth);
													  });
													});
													$vacation =  $query->get();
												}
												?>
												<td>
													@if(count($vacation)>0)
														<table>
														<tbody>
													   @foreach($vacation as $itemV)
															<tr>
																@if ($itemV->type == 3)
																	<td>болничен </td>
																@endif
																@if ($itemV->type == 2)
																	<td>неплатен отпуск</td>
																@endif
																@if ($itemV->type == 1)
																	<td>платен отпуск</td>
																@endif
																<td>{{$itemV->start_date}}</td>
																<td>{{$itemV->end_date}}</td> 
																<td>{{$itemV->comment}}</td>
															</tr>
														@endforeach
														</tbody>
														</table>
													@else
														---
													@endif
												</td>
												<td>{{$item->total}}</td>
												<?php if (isset($newSumArray[$item->ID])) { ?>
												<td> {{round($newSumArray[$item->ID] ,2)}} </td>
												<? } else { ?>
												<td></td>
												<? } ?>
											</tr>
										@endforeach
								@endif
                               </tbody>
                            </table>
						@endif
                          
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>