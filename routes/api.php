<?php

use Illuminate\Support\Facades\Route;

// Auth Module Routes
require __DIR__.'/../app/Modules/Auth/Routes/api.php';

// Projects Module Routes
require __DIR__.'/../app/Modules/Projects/Routes/api.php';

// Destinations Module Routes
require __DIR__.'/../app/Modules/Destinations/Routes/api.php';
