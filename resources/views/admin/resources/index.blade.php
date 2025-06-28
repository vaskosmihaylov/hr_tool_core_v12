@extends('layouts.backend')

@section('content')
    <div class="container">
        <div class="row">
            @include('admin.sidebar')

            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">Resources</div>
                    <div class="card-body">
                        <a href="{{ url('/admin/resources/create') }}" class="btn btn-success btn-sm" title="Add New Resource">
                            <i class="fa fa-plus" aria-hidden="true"></i> Add New
                        </a>

                        {!! Form::open(['method' => 'GET', 'url' => '/admin/resources', 'class' => 'form-inline my-2 my-lg-0 float-right', 'role' => 'search'])  !!}
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Search...">
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
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th><th>Url</th><th>Type</th><th>Permission</th><th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($resources as $item)
                                    <tr>
                                        <td>{{$item->id}}</td>
                                        <td><a href="{{ url('/admin/resources', $item->id) }}">{{ $item->value }}</a></td>
                                        <td>
                                            @switch($item->type)
                                                @case(1)
                                                Relative
                                                @break

                                                @case(2)
                                                Absolute
                                                @break

                                                @default
                                            @endswitch
                                        </td>
                                        <td>
                                            @if ($item->permission)
                                                {{$item->permission->name}}
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ url('/admin/resources/' . $item->id) }}" title="View Resource"><button class="btn btn-info btn-sm"><i class="fa fa-eye" aria-hidden="true"></i></button></a>
                                            <a href="{{ url('/admin/resources/' . $item->id . '/edit') }}" title="Edit Resource"><button class="btn btn-primary btn-sm"><i class="fa fa-pencil-square-o" aria-hidden="true"></i></button></a>
                                            {!! Form::open([
                                                'method' => 'DELETE',
                                                'url' => ['/admin/resources', $item->id],
                                                'style' => 'display:inline'
                                            ]) !!}
                                                {!! Form::button('<i class="fa fa-trash-o" aria-hidden="true"></i>', array(
                                                        'type' => 'submit',
                                                        'class' => 'btn btn-danger btn-sm',
                                                        'title' => 'Delete Resource',
                                                        'onclick'=>'return confirm("Confirm delete?")'
                                                )) !!}
                                            {!! Form::close() !!}
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div class="pagination"> {!! $resources->appends(['search' => Request::get('search')])->render() !!} </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
