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

</style>
    <div class="container-fluid">
        <div class="row">
            @include('service::sidebar')
            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
                <div class="card">
                    <div class="card-header">
						<fieldset class='tab' id='tabs'>
							<button class="tablinks"  onclick="showFormWorkers()"><a   href="{{ url('/service/reports') }}">Обща справка</a></button>|
							@if((\Illuminate\Support\Facades\Auth::user()->hasRole('admin'))
									|| (\Illuminate\Support\Facades\Auth::user()->hasRole('Human Resource Management')))
								<button class="tablinks"  onclick="showFormWorkers()"><a @ href="{{ url('/service/reports/workers') }}">Работници по  месец</a></button>|
							@endif
							<button class="tablinks"><a class='active' href="{{ url('/service/reports/workerplace') }}">Статус обекти </a>	</button>														
						</fieldset>
                    </div>
					@if($errors->any())
						<p class="text-danger">{{$errors->first()}}</p>
					@endif
					<div class="card-body common-form reports">
							<form action="/service/reports/worplace">
								<div class="table-filters">
									<select name="month_id" class="form-control mb-2" >
										<option value="novalue" @if($month_id =='novalue') selected  @endif>Избери месец</option>
										@foreach ($months as $object)
											<option value="{{ $object['id']}}" @if(($month_id == $object['id']) 
													and ($month_id!=null) and ($month_id !='novalue')) selected  @endif>
												{{$object['name'] }}
											</option>
										@endforeach
									</select>
									
									<select name="year_id" class="form-control mb-2">
										@foreach ($years as $object)
											<option value="{{ $object['id']}}" @if(($year_id == $object['id']) 
													and ($year_id!=null) and ($year_id !='novalue')) selected  @endif  test="{{$year_id}}">
												{{$object['id'] }}
											</option>
										@endforeach
									</select>
									<select name="status_id" class="form-control mb-2">
										<option value="novalue" @if($status_id=='novalue') selected  @endif>Избери статус</option>
										@foreach ($workplaceStatuses as $object)
											<option value="{{ $object['id']}}" @if(($status_id == $object['id']) 
													and ($status_id!=null) and ($status_id !='novalue')) selected  @endif >
												{{$object['name'] }}
											</option>
										@endforeach
									</select>
								</div>
								<div id='myDIV' class="input-group px-0 col-sm-6">
									<span class="input-group-append">
										<button class="btn btn-secondary search-btn" type="submit">
											<i class="fa fa-search"></i>
										</button>
									</span>
								</div>
							</form>
						&nbsp;
						@if(count($workerRecorods)==0)
							<span id='No-data' style= 'color:red'>Няма данни</span>
						@else
							{!! Form::close() !!}
							<div class="table-responsive">
								<?php $sumPerHourPerson = 0; ?>
								<table id='resultTable' class="table table-striped table-hover">
									<thead class="thead-dark">
										<tr>
											<th>Обект</th>
											<th>Статус</th>
											<th>Линк</th>
										</tr>
									</thead>
									<tbody>
									@if(!empty($workerRecorods))
										@foreach($workerRecorods as $value=>$item)
											<tr>
												<td>{{$item->workPlaceName}}</td>
												@if($item->status == 3)
													<td>приключен</td>
												@else
													<td>активен</td>
												@endif
												@if($item->status == 3)
													<td><a  href="{{ url('/service/archive/'.$item->work_place_id.'/'.$month_id.'-'.$year_id) }}">отвори</a></td>
												@else
													<td><a  href="{{ url('/service/presence/show/'.$item->work_place_id.'/'.$month_id.'-'.$year_id) }}">отвори</a></td>
												@endif
											</tr>
										@endforeach
									@endif
								   </tbody>
								</table>
								<?php $params = \Illuminate\Support\Facades\Request::all();?>
								<div class="pagination"> {!! $workerRecorods->appends($params)->render(); !!} </div>
						@endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
