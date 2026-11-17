<?php

use Illuminate\Support\Facades\Schedule;

// Uruchamiane przez crona na serwerze: php artisan schedule:run (co minutę).
Schedule::command('tobym:daily')->dailyAt('04:10');
