@extends('layouts.backend')

@section('content')
    <div class="container-fluid">
        <div class="row main-content">
         @include('service::sidebar')
             <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
                <div class="card">
                    <div class="card-header">
						Празници
					</div>
					<div class="card-body common-form">
					<a href="{{ url('/service/worker') }}" title="Back">
                            <button class="btn btn-warning btn-md mb-3">
                                <i class="fa fa-arrow-left" aria-hidden="true"></i> 
                                Назад
                            </button>
                    </a>
					</div>
					<div class="alert alert-success alert-block" style='text-align: center !important;'>
						<p class="text-left"></p>
						<p class="text-right"></p>
                       {{$holidaysResult}}
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Дата</th>
										<th>Коментар</th>
									</tr>
								</thead>
								<tbody>
								@foreach($holidays as $item)
									<tr>
										<td>{{$item->date}}</td>
										<td>{{$item->comment}}</td>	
                                @endforeach
                                </tbody>
                            </table> 
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
