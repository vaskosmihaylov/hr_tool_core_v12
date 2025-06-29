<input type="checkbox" class="hidden-input-toggle" id="show-menu">
<div class="custom-nav">
	<div class="card">
			<div class="card-body">
				<ul class="nav flex-column" role="tablist">
					<li class="nav-item custom-nav__item" role="presentation">
						<a class="nav-link" href={{ url('/service/worker') }}>
							Работници
						</a>
					</li>
					@if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('service/region'))
						<li class="nav-item custom-nav__item" role="presentation">
							<a class="nav-link" href={{ url('/service/region') }}>
								Региони
							</a>
						</li>
					@endif
					@if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('service/client'))
						<li class="nav-item custom-nav__item" role="presentation">
							<a class="nav-link" href={{ url('/service/client') }}>
								Клиенти
							</a>
						</li>
					@endif
					@if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('service/workplace'))
						<li class="nav-item custom-nav__item" role="presentation">
							<a class="nav-link" href={{ url('/service/workplace') }}>
								Обекти
							</a>
						</li>
					@endif
					@if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('service/approvement'))
						<li class="nav-item custom-nav__item" role="presentation">
							<a class="nav-link" href="{{ url('/service/approvement') }}">
								Одобрения
							</a>
						</li>
					@endif
					@if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('service/presence'))
						<li class="nav-item custom-nav__item" role="presentation">
							<a class="nav-link" href="{{ url('/service/presence') }}">
								Присъствена форма
							</a>
						</li>
					@endif
					@if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('service/users'))
						<li class="nav-item custom-nav__item" role="presentation">
							<a class="nav-link" href="{{ url('/service/users') }}">
								Потребители
							</a>
						</li>
					@endif
					@if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('service/history'))
						<li class="nav-item custom-nav__item" role="history">
							<a class="nav-link" href="{{ url('/service/history') }}">
								История
							</a>
						</li>
					@endif
					@if (\Illuminate\Support\Facades\Auth::user()->hasPermissionUrl('service/archive'))
						<li class="nav-item custom-nav__item" role="archive">
							<a class="nav-link" href="{{ url('/service/archive') }}">
								Архив
							</a>
						</li>
					@endif
					<li class="nav-item custom-nav__item" role="report">
						<a class="nav-link" href="{{ url('/service/reports') }}">
							Справки
						</a>
					</li>
				</ul>
			</div>
		</div>
		<br/>
	</div>
	
<label class="nav-open-bg" for="show-menu"></label>