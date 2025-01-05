<?php

use Illuminate\Support\Facades\Route;

Route::get('/api-generator',fn() => (new \EcliPhp\ApiGenerator\Class\ExtractRoutes())->all());
Route::get('/testrsfasdfasdfsdf',fn() => 'dsfasfasdfds');
