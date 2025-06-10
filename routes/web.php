<?php
//
//use Illuminate\Support\Facades\Route;
//use App\Http\Controllers\HomeController;
//
//Route::get('/', function () {
//    return view('welcome');
//});
//
//Auth::routes();
//
//Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
//
//Auth::routes();
//
//Route::get('/home', [HomeController::class, 'index'])
//    ->name('home');
//Route::get('/messages', [HomeController::class, 'messages'])
//    ->name('messages');
//Route::post('/message', [HomeController::class, 'message'])
//    ->name('message');


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;

const ROUTE_HOME = 'home';
const ROUTE_MESSAGES = 'messages';
const ROUTE_MESSAGE = 'message';

Route::get('/', fn() => view('welcome'));

Auth::routes();

Route::controller(HomeController::class)->group(function () {
    Route::get('/home', 'index')->name(ROUTE_HOME);
    Route::get('/messages', 'messages')->name(ROUTE_MESSAGES);
    Route::post('/message', 'message')->name(ROUTE_MESSAGE);
});
