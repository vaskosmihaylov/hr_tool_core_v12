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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<link href="{{ URL::asset('css/style.css') }}" rel="stylesheet" type="text/css" >
</head>
<body>
    <div id="app">
        <nav>
            <div class="container-fluid">
                <div class="header row">
                    <div class="col-12 header__wrap pt-4">
                        <a class="header__logo col-sm-9" href="{{ url('/') }}"></a>

                        <div class="navbar-collapse col-lg-2 col-md-3 col-sm-3 header__nav pr-0" id="navbarSupportedContent">

                            <ul class="navbar-nav ml-auto">
                                <!-- Authentication Links -->
                                @guest
                                    <li><a class="nav-link" href="{{ url('/login') }}">Login</a></li>
                                    <li><a class="nav-link" href="{{ url('/register') }}">Register</a></li>

                                @else

									<li><a  class="nav-link" href="#" role="button">
												   {{ Auth::user()->name }}
									</a>
									<a class="nav-link"  href="{{ url('/logout') }}"
									   onclick="event.preventDefault();
													 document.getElementById('logout-form').submit();">
										Изход
									</a>
									</li>
									<form id="logout-form" action="{{ url('/logout') }}" method="POST" >
										@csrf
									</form>
										</div>
								@endguest
                            </ul>
                        </div>
                    </div>

                </div>
            </div>
        </nav>

        <main class="py-4">
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


        <footer class="container-fluid footer py-4">
            <div class="row">
                <div class="footer__wrap">
                    <div class="footer__left col-lg-6 col-md-6 col-sm-12 mb-3">
                        <span> &copy; Viki Services 2019  </span>
                    </div>
                    <div class="footer__right col-lg-6 col-md-6 col-sm-12">
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
	<script type="text/javascript" href="https://code.jquery.com/jquery-3.5.1.js"> </script>
    <script type="text/javascript" href='https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js'> </script>
    <script type="text/javascript" href="https://cdn.datatables.net/keytable/2.6.2/js/dataTables.keyTable.js"></script>
    <script type="text/javascript" href="https://cdn.datatables.net/fixedcolumns/3.3.1/js/dataTables.fixedColumns.min.js"> </script>
</body>
</html>
