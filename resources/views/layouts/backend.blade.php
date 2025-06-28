<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Styles -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
	<link href="{{ URL::asset('css/font-awesome.min.css') }}" rel="stylesheet" type="text/css" >
    <link href="{{ URL::asset('css/style.css') }}" rel="stylesheet" type="text/css" >
</head>
<body>
<div id="app">
    <nav class="site-nav-wrapper pt-2 py-2">
        <div class="site-nav">
            <div class="container-fluid">
                <div class="header row">
                    <div class="col-sm-12 col-md-4 header-column">
                        <label for="show-menu" class=" show-menu toggle-nav-js">
                            <div class="show-menu-icon">
                                <svg width="24px" height="17px" viewBox="0 0 24 17" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                    <title>show-menu-icon</title>
                                    <g id="Symbols" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                        <g id="NavBar-Mobile" transform="translate(-333.000000, -21.000000)" fill="#FFFFFF">
                                            <g id="Group-2" transform="translate(333.000000, 21.000000)">
                                                <rect id="Rectangle" x="0" y="0" width="24" height="3"></rect>
                                                <rect id="Rectangle" x="0" y="7" width="24" height="3"></rect>
                                                <rect id="Rectangle" x="0" y="14" width="24" height="3"></rect>
                                            </g>
                                        </g>
                                    </g>
                                </svg>
                            </div>
                            <span class="hidden-title">Mеню</span>
                        </label>
                    </div>
                    
                    <div class="col-sm-12 col-md-4 header-column">
                        <a class="header__logo" href="{{ url('/') }}"></a>
                    </div>
                    
                    <div class="col-sm-12 col-md-4 header-column">
                        <div class="header__nav pr-0" id="navbarSupportedContent">
                            <!-- Right Side Of Navbar -->
                            <ul class="navbar-nav">
                                <!-- Authentication Links -->
                                @guest
                                    <li><a class="nav-link" href="{{ url('/login') }}">Login</a></li>
                                    <li><a class="nav-link" href="{{ url('/register') }}">Register</a></li>

                                @else
                                    <li class="nav-item admin-menu">
                                        <div class="pr-3" href="#">
                                            {{ Auth::user()->name }} <span class="caret"></span>
                                        </div>

                                        <a href="{{ url('/logout') }}" onclick="event.preventDefault();
                                                document.getElementById('logout-form').submit();">
                                            Изход
                                        </a>

                                        <form id="logout-form" action="{{ url('/logout') }}" method="POST"
                                                style="display: none;">
                                            @csrf
                                        </form>
                                    </li>
                                @endguest
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="mb-4 body-content @isset($type) {{ $type }} @endisset">
        @if (Session::has('flash_message'))
            <div class="container">
                <div class="alert alert-success">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    {{ Session::get('flash_message') }}
                </div>
            </div>
        @endif

        @yield('content')
    </main>


    <footer class="container-fluid footer py-3">
        <div class="row">
            <div class="footer__wrap">
                <div class="footer__left col-lg-6 col-md-6 mb-3">
                    <span> &copy; Viki Services 2025  </span>
                </div>
                <div class="footer__right col-lg-6 col-md-6">
                    <a href="http://code-nest.com/">
                        <span class="mr-3 mb-3"> Powered by </span>
                        <div class="footer__logo"></div>
                    </a>
                </div>
            </div>
        </div>
    </footer>

</div>
<script type="text/javascript" src="{!! URL::asset('script/app/validator.js') !!}"></script>
<script type="text/javascript" src="{{ URL::asset('js/main.js') }}"></script>
<script type="text/javascript" href="https://code.jquery.com/jquery-3.5.1.js"></script>
<script type="text/javascript" href='https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js'></script>
<script type="text/javascript" href="https://cdn.datatables.net/fixedcolumns/3.3.1/js/dataTables.fixedColumns.min.js"></script>
<script type="text/javascript">
    //tinymce.init({
    //   selector: '.crud-richtext'
    // });
</script>
<script type="text/javascript">
    //  $(function () {
    // Navigation active
    //    $('ul.navbar-nav a[href="{{ "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]" }}"]').closest('li').addClass('active');
    //  });
</script>

@yield('scripts')
</body>
</html>
