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

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group(['prefix' => 'users'], function() use ($router) {
    // TODO: authenticated routes
    $router->post('/', 'UserController@store');
    $router->get('/', 'UserController@index');
    $router->get('/{id:[0-9]+}', 'UserController@show');
    $router->put('/{id:[0-9]+}', 'UserController@update');
    $router->delete('/{id:[0-9]+}', 'UserController@delete');
});

$router->group(['prefix' => 'is'], function() use ($router) {
    // TODO: authenticated routes
    $router->post('/subscriber/{id:[0-9]+}', 'UserController@subscriber');
});

$router->post('/login', 'UserController@login');