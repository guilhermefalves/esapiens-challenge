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

$router->group(['prefix' => 'comments'], function() use ($router) {
    // TODO: authenticated routes
    $router->post('/', 'CommentController@store');
    $router->get('/post/{postID:[0-9]+}', 'CommentController@indexByPost');
    $router->get('/user/{userID:[0-9]+}', 'CommentController@indexByUser');
    $router->delete('/{id:[0-9]+}', 'CommentController@delete');
    $router->delete('/post/{postID:[0-9]+}', 'CommentController@deleteByPost');
    $router->delete('/{postID:[0-9]+}/{userID:[0-9]+}', 'CommentController@deleteByPostAndUser');

});
