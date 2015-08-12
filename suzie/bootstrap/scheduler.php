<?php

/*
* Some setup
*/
define('DOING_CRON', true);
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';

/**
 * Set timeout.
 */
set_time_limit(0);

/*
 * Build root path
 */
$rootPath = realpath(__DIR__.'/../..');

/*
 * Boot WordPress
 */
require_once $rootPath.'/public/wordpress/wp-load.php';

/*
 * Boot Scheduler
 */
$schedule = new Suzie\Scheduler();

/**
 * Include the Schedule.
 */
require $rootPath.'/public/schedule.php';

/*
 * Fire Due Events
 */
$schedule->fire();
