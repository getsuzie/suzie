<?php

namespace Suzie;

/**
 * Class Cdn.
 */
class Cdn
{
    /**
     * @var
     */
    protected $remoteUrl;

    /**
     * @var
     */
    protected $siteUrl;

    /**
     * @var
     */
    protected $cdnEnabled;


    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->setVars();
        $this->setHooks();
    }

    /**
     * Sets up some basic variables.
     */
    protected function setVars()
    {
        $this->siteUrl = rtrim(getenv('SITE_URL'), '/');
        $this->remoteUrl = getenv('CDN_SITE_URL');
        $this->cdnEnabled = getenv('CDN_SITE_ENABLED') === 'true' ? true : false;
    }

    /**
     * Hook into WordPress using various hooks/actions.
     */
    protected function setHooks()
    {
        add_filter('script_loader_src', [$this, 'setUrl']);
        add_filter('style_loader_src', [$this, 'setUrl']);
    }

    /**
     * Replaces an enqueue url with remote url.
     *
     * @param $url
     *
     * @return mixed
     */
    public function setUrl($url)
    {
        if (is_admin() || !$this->cdnEnabled) {
            return $url;
        }

        return str_replace($this->siteUrl.'/', $this->remoteUrl, $url);
    }
}
