<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->group(['prefix' => 'transaction'], function() use ($router) {
    // TODO: authenticated routes
    $router->post('/', 'TransactionController@store');
    $router->delete('/{id:[0-9]+}', 'TransactionController@delete');

    $router->post('/confirm/{id:[0-9]+}', 'TransactionController@confirm');
});

$router->post('/balance', 'TransactionController@balance');
