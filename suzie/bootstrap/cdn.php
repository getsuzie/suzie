<?php

define('DOING_CDN', true);

/**
 * Set timeout.
 */
set_time_limit(0);

/*
 * Build root path
 */
$rootPath = realpath(__DIR__.'/../..');

/*
 * Boot WordPress Config
 */
require $rootPath.'/public/wp-config.php';

/*
 * Boot Sync & CDN
 */
$sync = new Suzie\Sync(true);
$cdn = new Suzie\Cdn($sync->remoteUrl, true);

/*
 * Loop assets and move them to remote
 */
if(getenv('CDN_ENABLED') == 'true') {
    $assets = $cdn->getApprovedAssets();

    foreach ($assets as $url) {
        echo "Uploading to CDN: {$url}" . PHP_EOL;

        $sync->moveToRemote($url);
    }
}
