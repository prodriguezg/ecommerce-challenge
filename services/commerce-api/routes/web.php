<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/docs', function () {
    abort_unless(config('api.documentation.enabled'), 404);

    return response(file_get_contents(public_path('api-docs.html')))
        ->header('Content-Type', 'text/html; charset=UTF-8');
})->name('api.docs');
