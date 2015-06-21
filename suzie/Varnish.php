<?php

namespace Suzie;

/**
 * Class Varnish.
 */
class Varnish
{
    /**
     * @var string
     */
    protected $path;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->path = getenv('SITE_URL').'.*';

        if (getenv('VARNISH_PATH')) {
            $this->path = getenv('VARNISH_PATH');
        }

        $enabled = getenv('CDN_ENABLED') === 'true' ? true : false;

        if ($enabled) {
            $this->setHooks();
        }
    }

    /**
     *  Attach purge WordPress events.
     */
    protected function setHooks()
    {
        add_action('save_post', [$this, 'purge']);
        add_action('deleted_post', [$this, 'purge']);
        add_action('trashed_post', [$this, 'purge']);
        add_action('edit_post', [$this, 'purge']);
        add_action('delete_attachment', [$this, 'purge']);
        add_action('switch_theme', [$this, 'purge']);
    }

    /**
     * Fire a remote request to clear varnish cache.
     */
    public function purge()
    {
        wp_remote_request($this->path, [
            'method' => 'PURGE',
            'headers' => ['X-Purge-Method' => 'regex'],
        ]);
    }
}
