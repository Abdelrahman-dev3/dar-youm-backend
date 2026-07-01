<?php

use Illuminate\Support\Facades\Route;

Route::get('/login', function () {
    return redirect((string) env('FRONTEND_URL', 'http://daryum-app.city2tec.com') . '/login');
})->name('login');

Route::get('/', function () {
    return view('welcome');
});
