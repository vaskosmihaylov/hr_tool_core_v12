@extends('layouts.backend')

@section('content')
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css" rel="stylesheet"/>
    <div class="container-fluid">
        <div class="row">
         @include('service::sidebar')
            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 table-content">
                <div class="card">
					<div class="card-header">Коментари</div>
					<div class="card-body common-form">
					@if($errors->any())
						<p class="text-danger">{{$errors->first()}}</p>
					@endif
					<div class="form-group">
					<a href="{{ url('/service/approvement') }}" title="Back">
						<button class="btn btn-warning btn-md">
							<i class="fa fa-arrow-left" aria-hidden="true"></i>
							Назад
						</button>
					</a>
					</div>
					<form method="POST" action="{{route('service.approvement.comment.add')}}"  name="newForm" role="form" enctype="multipart/form-data" id="edit-formcomment">
						{{ csrf_field() }}
					<div class="form-group">
						<input size='50' name="comment" type="text" min='1' maxlength="350" class="form-control" required="" placeholder="Нов коментар...">
						<input size='50' name="id" type="hidden" value="{{ $id }}" >
					</div>
					<div class="form-group">
						 {!! Form::submit('Добави', ['class' => 'btn btn-primary']) !!}
					</div>
					</form>
					<div class="table-responsive">
						<table class="table table-striped table-hover">
							<thead class="thead-dark">
								<tr>
									<th>Коментар</th>
									<th>От дата</th>
									<th>Добавен от</th>	
								</tr>
							</thead>
							<tbody>
							@foreach($approvementComments as $item)
								<tr>
									<td>{{$item->comment}}</td> 
									<td>{{$item->created_at}}</td>
									<td>{{$item->createdBy->name}}</td>
								</tr>
							@endforeach
							</tbody>
						</table>
					
						<div class="pagination"> {!! $approvementComments->appends(['search' => Request::get('search')])->render() !!} </div>
					</div>
				</div>
                </div>
            </div>
        </div>
    </div>
@endsection