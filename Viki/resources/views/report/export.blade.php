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
						
                           <table class="table table-striped table-hover" border='1'>
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Име</th>
										<th>Презиме</th>
										<th>Фамилия</th>
                                        <th>ЕГН</th>
										<th>Обект</th>
										<th>Клиент</th>
										<th>Регион</th>
										<th>Дейност</th>
                                        <th>Изработени часове</th>
										 <th>Бонус</th>
                            <th>Наказание</th>
                            <th>Сума</th>
                            <th>Сума + бонус - наказание</th>
                                      
                                    </tr>
                                </thead>
                                <tbody>
								body>
                        <?php
                        $sumTotalHours = 0;
                        $sumTotal = 0;
                        $bonusTotal = 0;
                        $bonusPayCutTotal = 0;
             
                        ?>
								@if(!empty($workerRecorods))
									@foreach($workerRecorods as $item)
										<tr>
											<td>{{$item->name}}</td>
											<td>{{$item->middle_name}}</td>
											<td>{{$item->family_name}}</td>
											<td>{{$item->egn}}</td>
											<td>{{$item->workPlaceName}}</td> 
											<?php $name = '';
												$name = viki\Service\Models\Elequent\Client::find($item->clId);
												if(!empty($name)){ $name = $name->name; }
												if(empty($item->selectedDate)) { $item->selectedDate = $year_id."-".$month_id."-01";}
											 ?>
											<td>{{$name}}</td>
											<?php $region = '';
												$region = viki\Service\Models\Elequent\Region::find($item->regId);
												if(!empty($region)){ $region = $region->name; }
											 ?>
											<td>{{$region}}</td>
											<td>{{$item->activity}}</td>
											<?php $sumTotalHours = $sumTotalHours + $item->total;?>
											<td>{{$item->total}}</td>
															<?php
											$bonus = viki\Service\Models\Elequent\WorkerBonus::getBonusCutByMonth($item->work_place_id, $item->worker_id, $item->selectedDate, viki\Service\Models\Elequent\WorkerBonus::BONUS);
											$bonusTotal = $bonusTotal + $bonus;
											?>
											<td>{{$bonus}}</td>
											<?php
											$paycut = viki\Service\Models\Elequent\WorkerBonus::getBonusCutByMonth($item->work_place_id, $item->worker_id, $item->selectedDate, viki\Service\Models\Elequent\WorkerBonus::PAY_CUT);
											$bonusPayCutTotal = $bonusPayCutTotal + $paycut;
											?>
											<td>{{$paycut}}</td> 
											<?php if (isset($arraySum[$item->ID])) { ?>
												<td>{{number_format(round($arraySum[$item->ID] ,2), 2, ',', ' ')}} </td>
											<? } else {?>
												<td> </td>
											<? } ?>
											<?php if (isset($arraySum[$item->ID])) { ?>
											 <td> {{number_format(((round($arraySum[$item->ID] ,2)+ $bonus) - $paycut),2, ',', ' ')}} </td>
											 <? } else {?>
												<td> </td>
											<? } ?>
											<?php 
											$sumPerHour = 0;
											if(!empty($arraySum)) {
												foreach($arraySum as $key=>$sum) {
													if ($item->ID == $key) {
														$sumPerHour = isset($arraySum[$item->ID])? $arraySum[$item->ID] : 0;
													}
												}
											}?>
											<?php $sumTotal = $sumTotal + round($sumPerHour,2);?>
											
										</tr>
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
									<?php $sumTotalHours = str_replace('.', ',', $sumTotalHours); ?>
                                     <td>{{$sumTotalHours}}</td>
									 <?php $sumTotalOld = $sumTotal;
                            $sumTotal = str_replace('.', ',', $sumTotal); ?>
                            <td>{{$bonusTotal}}</td>
                            <td>{{$bonusPayCutTotal}}</td>
                            <td>{{$sumTotal}}</td>
                            <?php $sumOfAll = ($sumTotalOld + $bonusTotal) - $bonusPayCutTotal;
                            $sumOfAll = str_replace('.', ',',$sumOfAll); ?>
                            <td>{{$sumOfAll}}</td>
								</tr>
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