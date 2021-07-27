<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('miratio/sync', 'Api\MiratioController@sync');
Route::post('channels', 'Api\CustomersController@index');
Route::get('channels/{id}', 'Api\CustomersController@show');
Route::put('channels/{id}', 'Api\CustomersController@update');
Route::delete('channels/{id}', 'Api\CustomersController@destroy');
Route::post('customers/sync', 'Api\CustomersController@sync');