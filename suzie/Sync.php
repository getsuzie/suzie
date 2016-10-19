<?php

namespace Suzie;

/**
 * Class Cdn.
 */
class Sync
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        switch(getenv('SYNC')) {
            case 's3':
            case 'sftp':
                return new Sync\Basic();
                break;
            case 'gcs':
                return new Sync\GoogleCloudStorage();
                break;
            default:
                return false;
                break;
        }

        return $suzie;
    }


}
