@extends('layouts.backend')

@section('content')
    <div class="container">
        <div class="row">
            @include('admin.sidebar')

            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">Page {{ $page->id }}</div>
                    <div class="card-body">

                        <a href="{{ url('/admin/pages') }}" title="Back"><button class="btn btn-warning btn-sm"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back</button></a>
                        @if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('admin/pages/edit'))
                            <a href="{{ url('/admin/pages/edit/' . $page->id) }}" title="Edit Page"><button class="btn btn-primary btn-sm"><i class="fa fa-pencil-square-o" aria-hidden="true"></i> Edit</button></a>
                        @endif
                        @if(\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('admin/pages/delete'))
                            {!! Form::open([
                                'method'=>'DELETE',
                                'url' => ['admin/pages/delete', $page->id],
                                'style' => 'display:inline'
                            ]) !!}
                                {!! Form::button('<i class="fa fa-trash-o" aria-hidden="true"></i> Delete', array(
                                        'type' => 'submit',
                                        'class' => 'btn btn-danger btn-sm',
                                        'title' => 'Delete Page',
                                        'onclick'=>'return confirm("Confirm delete?")'
                                ))!!}
                            {!! Form::close() !!}
                        @endif
                        <br/>
                        <br/>

                        <div class="table-responsive">
                            <table class="table">
                                <tbody>
                                    <tr>
                                        <th>ID</th><td>{{ $page->id }}</td>
                                    </tr>
                                    <tr><th> Title </th><td> {{ $page->title }} </td></tr><tr><th> Content </th><td> {{ $page->content }} </td></tr>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
