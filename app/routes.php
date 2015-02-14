<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

Route::get('/', function()
{
	return View::make('hello');
});

Route::group(array('prefix' => 'api/v1'), function () {
	Route::post('kurs/{id}/vote', 'KursController@vote');
  Route::get('kurs/day-averages', 'KursController@dayAverages');
  Route::get('kurs/places', 'KursController@places');
  Route::get('kurs/day-averages/{dayBack}', 'KursController@dayAverages');
	Route::resource('kurs', 'KursController');
	Route::get('db', function () {
		$url = parse_url(getenv("CLEARDB_DATABASE_URL"));
		$server = $url["host"];
		return $server;
	});
});

Route::get('/', function()
{
	// we dont need to use Laravel Blade
	// we will return a PHP file that will hold all of our Angular content
	// see the "Where to Place Angular Files" below to see ideas on how to structure your app
	return View::make('dist/index'); // will return app/views/index.php
});

App::missing(function($exception)
{
	return View::make('dist/index');
});