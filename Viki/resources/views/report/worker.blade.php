@extends('layouts.backend')

@section('content')
    <div class="container-fluid">
        <div class="row">
            @include('service::sidebar')
             <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
                <div class="card">
                    <div class="card-header">
						Справка за  <b> <?php echo ($month_id."-".$year_id);?></b> по работници
                    </div>
					<div class="card-body common-form">
						<a href="{{ url('/service/reports/workers') }}" title="Назад">
                            <button class="btn btn-warning btn-md mb-3">
                                <i class="fa fa-arrow-left" aria-hidden="true"></i> 
                                Назад
                            </button>
                        </a>
						@if(count($workerRecords)>0)
						<div class="table-filters export">
							<?php if(!isset($client_id)) { $client_id = 'novalue';} ?>
							<?php if(!isset($egn)) { $egn = 'novalue';} ?>
							<a href="{{ url('service/reports/exportWorkerExcel', ['month_id' => $month_id , 'year_id' => $year_id , 'egn' => $egn ]) }}" 
							class="btn btn-success btn-md"><i class="fa fa-print" aria-hidden="true"></i> Свали excel</a>
						</div>
							
					</div>
                     {!! Form::close() !!}
						<div class="table-responsive">
							<?php $sumPerHourPerson = 0; ?>
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Име</th>
										<th>Презиме</th>
										<th>Фамилия</th>
                                        <th>ЕГН</th>
										<th>Отпуска</th>
                                        <th>Изработени часове</th>
										<th>Бонус</th>
									<th>Наказание</th>
									<th>Сума</th>
									<th>Сума+Б-Н</th>
                                    </tr>
                                </thead>
                                <tbody>							
									@if(!empty($workerRecords))
										@foreach($workerRecords as $value=>$item)
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
															@if($itemV)
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
															@endif
														@endforeach
														</tbody>
														</table>
													@else
														---
													@endif
												</td>
												<td>{{$item->total}}</td>
												 <?php
									if(empty($item->selectedDate)) {
										$item->selectedDate = $year_id ."-".$month_id."-01";
									}
										
                                $bonus = viki\Service\Models\Elequent\WorkerBonus::getBonusCutByMonth($item->work_place_id, $item->worker_id, $item->selectedDate, viki\Service\Models\Elequent\WorkerBonus::BONUS);
                                ?>
                                <td>{{$bonus}}</td>
<?php
$paycut = viki\Service\Models\Elequent\WorkerBonus::getBonusCutByMonth($item->work_place_id, $item->worker_id, $item->selectedDate, viki\Service\Models\Elequent\WorkerBonus::PAY_CUT);
?>
                                <td>{{$paycut}}</td>
                              <?php if(isset($newSumArray[$item->ID])) { ?>
													<td> {{round($newSumArray[$item->ID] ,2)}} </td>
												<?php } else {?>
												<td></td>
												<?php } ?>
									 <?php if(isset($newSumArray[$item->ID])) { ?>			                      
                                <td style="display: flex;justify-content: center;align-items: center;min-height: 140px;">{{round($newSumArray[$item->ID] ,2) + $bonus - $paycut}} </td>
										<?php } else {?>
												<td></td>
												<?php } ?>		
											</tr>
										@endforeach
								@endif
                               </tbody>
                            </table>
							<?php $params = \Illuminate\Support\Facades\Request::all();?>
							<div class="pagination"> </div
						@else
						<div style='color:red'>Няма данни</div>&nbsp;
						@endif
						
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
