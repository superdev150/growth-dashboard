<?php

use Illuminate\Support\Facades\Route;

// The dashboard is a React app; Laravel serves its shell and the JSON API.
Route::view('/', 'dashboard');
