@extends('layouts.backend')

@section('content')
    <div class="container">
        <div class="row">
            @include('admin.sidebar')

            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">Pages</div>
                    <div class="card-body">
                        @if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('admin/pages/create'))
                            <a href="{{ url('/admin/pages/create') }}" class="btn btn-success btn-sm" title="Add New Page">
                                <i class="fa fa-plus" aria-hidden="true"></i> Add New
                            </a>
                        @endif
                        {!! Form::open(['method' => 'GET', 'url' => '/admin/pages', 'class' => 'form-inline my-2 my-lg-0 float-right', 'role' => 'search'])  !!}
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Search..." value="{{ request('search') }}">
                            <span class="input-group-append">
                                <button class="btn btn-secondary" type="submit">
                                    <i class="fa fa-search"></i>
                                </button>
                            </span>
                        </div>
                        {!! Form::close() !!}

                        <br/>
                        <br/>
                        <div class="table-responsive">
                            <table class="table table-borderless">
                                <thead>
                                    <tr>
                                        <th>ID</th><th>Title</th><th>Content</th><th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($pages as $item)
                                    <tr>
                                        <td>{{ $item->id }}</td>
                                        <td>{{ $item->title }}</td><td>{{ $item->content }}</td>
                                        <td>
                                            @if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('admin/pages/show'))
                                                <a href="{{ url('/admin/pages/show/' . $item->id) }}" title="View Page"><button class="btn btn-info btn-sm"><i class="fa fa-eye" aria-hidden="true"></i></button></a>
                                            @endif
                                            @if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('admin/pages/edit'))
                                                <a href="{{ url('/admin/pages/edit/' . $item->id) }}" title="Edit Page"><button class="btn btn-primary btn-sm"><i class="fa fa-pencil-square-o" aria-hidden="true"></i></button></a>
                                            @endif
                                            @if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('admin/pages/delete'))
                                                {!! Form::open([
                                                    'method' => 'DELETE',
                                                    'url' => ['/admin/pages/delete', $item->id],
                                                    'style' => 'display:inline'
                                                    ]) !!}
                                                {!! Form::button('<i class="fa fa-trash-o" aria-hidden="true"></i>', array(
                                                            'type' => 'submit',
                                                            'class' => 'btn btn-danger btn-sm',
                                                            'title' => 'Delete Page',
                                                            'onclick'=>'return confirm("Confirm delete?")'
                                                )) !!}
                                                {!! Form::close() !!}
                                            @endif


                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div class="pagination-wrapper"> {!! $pages->appends(['search' => Request::get('search')])->render() !!} </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
