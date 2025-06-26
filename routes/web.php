<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Login route for middleware redirects - now redirects to working Filament login
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');
