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

$router->group(['prefix' => 'notification'], function() use ($router) {
    // TODO: authenticated routes
    $router->post('/', 'NotificationController@store');

    $router->get('/', 'NotificationController@index');
    $router->get('/new', 'NotificationController@indexNew');
    $router->get('/all', 'NotificationController@indexAll');
    $router->get('/{id:[0-9]+}', 'NotificationController@show');

    $router->put('/{id:[0-9]+}', 'NotificationController@update');
    $router->delete('/{id:[0-9]+}', 'NotificationController@delete');
});

// $router->group(['prefix' => 'has'], function() use ($router) {
//     // TODO: authenticated routes
//     $router->post('/sended/{id:[0-9]+}', 'NotificationController@sended');
//     $router->post('/viewed/{id:[0-9]+}', 'NotificationController@viewed');
// });