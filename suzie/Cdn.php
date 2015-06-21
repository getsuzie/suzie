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
    protected $approvedAssets;

    /**
     * @var
     */
    protected $cdnEnabled;

    /**
     * @var
     */
    protected $themePath;

    /**
     * @var
     */
    protected $pluginPath;

    /**
     * @var bool
     */
    protected $disableHooks;

    /**
     * Constructor.
     */
    public function __construct($remoteUrl, $disableHooks = false)
    {
        $this->remoteUrl = $remoteUrl;

        if(getenv('CDN_URL')) {
            $this->remoteUrl = getenv('CDN_URL');
        }

        $this->disableHooks = $disableHooks;
        $this->setVars();

        if (!$this->disableHooks) {
            add_filter('script_loader_src', [$this, 'setUrl']);
            add_filter('style_loader_src', [$this, 'setUrl']);
        }
    }

    /**
     * Sets up some basic variables.
     */
    protected function setVars()
    {
        $this->themePath = '/content/themes/'.SUZIE_CDN_THEME;
        $this->pluginPath = '/content/plugins/plugins';
        $this->siteUrl = rtrim(getenv('SITE_URL'), '/');
        $this->approvedAssets = $this->compileAssets(SUZIE_CDN_ASSETS);
        $this->cdnEnabled = getenv('CDN_ENABLED') === 'true' ? true : false;
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

        $compareUrl = strtok($url, '?');
        $compareUrl = preg_replace('(^https?:)', '', $compareUrl);

        if (in_array($compareUrl, $this->approvedAssets)) {
            return str_replace($this->siteUrl.'/', $this->remoteUrl, $url);
        }

        return $url;
    }

    /**
     * Generates an array of assets needed to be moved or urls modified.
     *
     * @param $assets
     *
     * @return array|mixed
     */
    protected function compileAssets($assets)
    {
        $assets = json_decode($assets);

        if (!$this->disableHooks) {
            $parsedSiteUrl = preg_replace('(^https?:)', '', $this->siteUrl);
        } else {
            $parsedSiteUrl = $this->siteUrl;
        }

        foreach ($assets as $key => $url) {
            $url = str_replace('{theme}', $this->themePath, $url);
            $url = str_replace('{plugin}', $this->pluginPath, $url);
            $assets[$key] = $parsedSiteUrl.$url;
        }

        return $assets;
    }

    /**
     * Returns approved assets.
     *
     * @return mixed
     */
    public function getApprovedAssets()
    {
        return $this->approvedAssets;
    }
}
