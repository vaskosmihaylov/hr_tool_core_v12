<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Simple login route for middleware redirects
Route::get('/login', function () {
    return '<h1>Please Login</h1><p><a href="/admin">Go to Admin Panel</a></p>';
})->name('login');
