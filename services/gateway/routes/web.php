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

$router->get('/docs', ['as' => 'docs', function () use ($router) {
    // TODO: preciso criar as docs do projeto
    return view('docs');
}]);

$router->group([
    'middleware' => 'validService',
    'prefix'     => '/{service:[a-z]+}[/{endpoint:[0-9|a-z\/0-9]+}]'
], function () use ($router) {
    $router->get('', 'ServiceController@handler');
    $router->post('', 'ServiceController@handler');
    $router->delete('', 'ServiceController@handler');
});
