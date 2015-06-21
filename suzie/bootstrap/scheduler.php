<?php

define('DOING_CRON', true);

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
require $rootPath.'/public/wordpress/wp-load.php';

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
