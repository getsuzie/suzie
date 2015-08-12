<?php

/*
* Some setup
*/
define('DOING_CDN', true);
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

    foreach ($assets as $file) {
        echo "Uploading to CDN: {$file}" . PHP_EOL;
        $sync->moveToRemote($file);
    }
}
