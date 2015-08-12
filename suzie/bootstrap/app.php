<?php

/**
 * Build root path.
 */
$rootPath = realpath(__DIR__.'/../..');

/*
 * Fetch .env or let the user know to add one
 */
if (!file_exists($rootPath.'/.env')) {
    die('Please add a .env file');
}

Dotenv::load($rootPath);

/***
 * Mailer
 */
function wp_mail($to, $subject, $message, $headers = '', $attachments = [])
{
    return Suzie\Mailer::boot($to, $subject, $message, $headers, $attachments);
}

function suzie()
{
    /*
     * Sync
     */
    $sync = new Suzie\Sync();

    /*
     * Cdn
     */
    new Suzie\Cdn($sync->remoteUrl);

    /*
     * Varnish
     */
    new Suzie\Varnish();

    do_action('suzie');
}
