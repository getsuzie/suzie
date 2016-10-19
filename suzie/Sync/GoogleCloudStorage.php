<?php

namespace Suzie\Sync;

use google\appengine\api\cloud_storage\CloudStorageTools;
use google\appengine\api\cloud_storage\CloudStorageException;
use google\appengine\api\app_identity\AppIdentityService;

/**
 * Class GoogleCloudStorage.
 */
class GoogleCloudStorage
{
    /**
     * @var string
     */
    public $remoteUrl;

     /**
     * @var string
     */
    public $bucketName;

    /**
     * @var bool
     */
    public $skipImageFilters;

    /**
     * Constructor.
     *
     * @param bool $disableHooks
     */
    public function __construct()
    {
        $this->setVars();
        $this->setHooks();
    }

    /**
     *  Sets up some basic variables.
     */
    protected function setVars()
    {
        $this->bucketName = getenv('GCS_BUCKET');
        $this->skipImageFilters = false;
    }

    /**
     * Hook into WordPress using various hooks/actions.
     */
    protected function setHooks()
    {
        add_filter('upload_dir', [$this, 'filterUploadDirectory']);
        add_filter('pre_option_uploads_use_yearmonth_folders', '__return_null');
        add_action('all_admin_notices', [$this, 'setFormFilters']);
        add_filter('plupload_default_settings', [$this, 'setGcsUploadUrlJS']);
        add_filter('plupload_init', [$this, 'setGcsUploadUrlJS']);
        add_filter('authenticate',  [$this, 'authenticate'], 1, 3);
        add_action('suzie', [$this, 'bootCustomAuth'], 1);
        add_filter('image_downsize', [$this, 'handleImageDownsize'], 1, 3);
        add_filter('wp_image_editors', [$this, 'filterImageEditor'], 1);
        add_filter('wp_calculate_image_srcset', [$this, 'attachmentLinkRewriteSrcSet'], 10, 5);
    }

    /**
     * Change the upload directory to point at our upload bucket
     */
    public function filterUploadDirectory($values)
    {
        if ( $this->skipImageFilters ) {
            return $values;
        }

        $default = stream_context_get_options( stream_context_get_default() );
        $gcs_opts = [
            'gs' => [
                'acl' => 'public-read',
            ],
        ];

        $context = array_replace_recursive( $default, $gcs_opts );
        stream_context_set_default( $context );

        $values = array(
            'path' => 'gs://' . $this->bucketName,
            'subdir' => '',
            'error' => false,
        );

        $publicUrl = CloudStorageTools::getPublicUrl('gs://' . $this->bucketName, true);

        $values['url'] = rtrim($publicUrl, '/');
        $values['basedir'] = $values['path'];
        $values['baseurl'] = $values['url'];

        return $values;
    }


     /**
     * Set filters for upload forms
     */
    public function setFormFilters()
    {
        add_filter('admin_url', [$this, 'setGcsUploadUrl'], 10, 2);
    }

    /**
     * Remove filters for upload forms
     */
    public function removeFormFilters()
    {
        remove_filter('admin_url', [$this, 'setGcsUploadUrl'], 10, 2);
    }

     /**
     * Change the upload form's URL to point to the GCS uploader
     */
    public function setGcsUploadUrl($url, $path)
    {
        $screen = get_current_screen();

        if ( $screen->parent_file == 'upload.php'
             && $path == 'media-new.php'
             && $screen->id == 'media'
        ) {
            $this->removeFormFilters();
            return $this->gcsUploadUrl($url);
        }

        return $url;
    }

    /**
     * Change the upload URL in the JS uploader to point to the GCS uploader
     */
    public function setGcsUploadUrlJS($settings)
    {
        $settings['url'] = $this->gcsUploadUrl($settings['url']);
        return $settings;
    }

    /**
     * Generate GCS Url to upload too
     */
    protected function gcsUploadUrl($url)
    {
        $userId = get_current_user_id();
        $maxUploadSize = wp_max_upload_size();
        $signResult = $this->signAuthKey(AUTH_KEY . $userId);
        $key = $signResult['key_name'];
        $sig = base64_encode($signResult['signature']);

        $options = [
            'gs_bucket_name' => $this->bucketName,
            'url_expiry_time_seconds' => 60 * 60 * 24
        ];

        if (is_int($maxUploadSize) && $maxUploadSize > 0) {
            $options['max_bytes_per_blob'] = $maxUploadSize;
        }

        $url = add_query_arg([
            'gae_auth_user' => $userId,
            'gae_auth_key' => $key,
            'gae_auth_signature' => urlencode($sig)
        ], $url);

        return CloudStorageTools::createUploadUrl( $url, $options );
    }

    /**
     * Generate simple Auth Key
     */
    protected function signAuthKey($key)
    {
        return AppIdentityService::signForApp($key);
    }

    /**
     * Set the $_COOKIE values for our custom authentication
     */
    protected function setFakeCookies($user_id)
    {
        $expiration = time() + apply_filters('auth_cookie_expiration', 2 * DAY_IN_SECONDS, $user_id, false);
        $secure = apply_filters('secure_auth_cookie', is_ssl(), $user_id);
        $secureLoggedInCookie = apply_filters('secure_logged_in_cookie', false, $user_id, $secure);

        if ($secure) {
            $authCookieName = SECURE_AUTH_COOKIE;
            $scheme = 'secure_auth';
        } else {
            $authCookieName = AUTH_COOKIE;
            $scheme = 'auth';
        }

        if ( !isset($_COOKIE[$authCookieName]) ) {
            $_COOKIE[$authCookieName] = wp_generate_auth_cookie( $user_id, $expiration, $scheme );
        }
        if ( !isset($_COOKIE[LOGGED_IN_COOKIE]) ) {
            $_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie( $user_id, $expiration, 'logged_in' );
        }
    }


    /**
     * Authenticate App Engine using the internal key and signature
     */
    public function authenticate($user, $username, $password)
    {
        if ( is_a( $user, 'WP_User' ) ) {
            return $user;
        }

        if ( !empty( $username ) || !empty( $password ) ) {
            return $user;
        }

        if ( empty( $_GET['gae_auth_user'] ) || empty( $_GET['gae_auth_key'] ) || empty( $_GET['gae_auth_signature'] ) ) {
            return $user;
        }

        $userId = absint( $_GET['gae_auth_user'] );
        $signResult = $this->signAuthKey(AUTH_KEY . $userId);

        if ( $signResult['key_name'] !== $_GET['gae_auth_key'] ) {
            return $user;
        }

        if ( base64_decode( $_GET['gae_auth_signature'] ) !== $signResult['signature'] ) {
            return $user;
        }

        $this->setFakeCookies( $userId );
        return new \WP_User( $userId );
    }


    /**
     * Ensure that we always authenticate correctly
     */
    public function bootCustomAuth()
    {
        $user = $this->authenticate(null, '', '');
        if ( $user ) {
            global $current_user;
            $current_user = $user;
        }
    }

    public function handleImageDownsize($data, $id, $size)
    {
        $file = get_attached_file($id);
        $intermediate = false;

        if (0 !== strpos( $file, 'gs://' )) {
            return $data;
        }

        if ( is_array($size) ) {
            $size = [
                'width' => $size[0],
                'height' => $size[1],
                'crop' => true
            ];

            $intermediate = true;
        } else {
            $imageSizes = $this->getAllImageSizes();

            if (!isset($imageSizes[$size])) {
                return false;
            }

            $size = $imageSizes[$size];
        }

        $url = $this->getUrlWithSizing($file, $size);

        return [
            $url,
            $size['width'],
            $size['height'],
            $intermediate
        ];
    }

    public function getUrlWithSizing($file, $size)
    {
         $options = [
            'secure_url' => true
        ];

        if (getenv('GCS_SERVE') == 'true') {
            $url = CloudStorageTools::getImageServingUrl($file, $options);
        } else {
            $url = CloudStorageTools::getPublicUrl($file, $options);
        }

        if (!empty($size['width']) && !empty($size['height'])) {
            $url .= '=w' . $size['width'] . '-h' . $size['height'];

            if ($size['crop']) {
                $url .= '-c';
            }
        } else {
            $url .= '=s0';
        }

        return $url;
    }

     /**
     * Return a list of images in WordPress
     */
    public function getAllImageSizes()
    {
        global $_wp_additional_image_sizes;
        $default_image_sizes = array( 'thumbnail', 'medium', 'large' );

        foreach ( $default_image_sizes as $size ) {
            $image_sizes[$size]['width'] = intval( get_option( "{$size}_size_w") );
            $image_sizes[$size]['height'] = intval( get_option( "{$size}_size_h") );
            $image_sizes[$size]['crop'] = get_option( "{$size}_crop" ) ? get_option( "{$size}_crop" ) : false;
        }

        if ( isset( $_wp_additional_image_sizes ) && count( $_wp_additional_image_sizes ) ) {
            $image_sizes = array_merge( $image_sizes, $_wp_additional_image_sizes );
        }

        return $image_sizes;
    }

     /**
     * Rewrite URLs for srcset
     */
    public function attachmentLinkRewriteSrcSet($sources, $size_array, $image_src, $image_meta, $attachment_id)
    {
        if (!is_array($sources)) {
            return $sources;
        }

        foreach ($sources as $key => $src) {

            $size = [
                'width' => '',
                'height' => '',
                'crop' => false
            ];

            $storedSize = false;

            foreach ($image_meta['sizes'] as $image) {
                if ( $src['descriptor'] == 'w' && $image['width'] == $src['value'] ) {
                    $storedSize = $image;
                    break;
                } elseif ( $src['descriptor'] == 'h' && $image['height'] == $src['value'] ) {
                    $storedSize = $image;
                    break;
                }
            }

            if ($storedSize) {

                $crop = true;

                if ( $storedSize['width'] == 0 || $storedSize['width'] == 9999 ) {
                    $storedSize['width'] = '';
                    $crop = false;
                }

                if ( $storedSize['height'] == 0 || $storedSize['height'] == 9999 ) {
                    $storedSize['height'] = '';
                    $crop = false;
                }

                $size = [
                    'width' => $storedSize['width'],
                    'height' => $storedSize['height'],
                    'crop' => $crop
                ];

            }

            $sources[$key]['url'] = $this->getUrlWithSizing(
                'gs://' . $this->bucketName . '/' . $image_meta['file'],
                $size);
        }

        return $sources;
    }

    /**
     * Add custom Image Editor to work around resized images being added
     * when GCS doesn't need them
     */
    public function filterImageEditor($editors)
    {
        return ['Suzie\Sync\GCS_Image_Editor_Imagick', 'Suzie\Sync\GCS_Image_Editor_GD'];
    }

}

 /**
 * Unfortunate way to stop resized image being created,
 * using intermediate_image_sizes_advanced causes empty metadata which
 * affects srcset images. Therefore this is best working solution so far.
 */
$rootPath = realpath(__DIR__.'/../..');
require_once $rootPath . '/public/wordpress/wp-includes/class-wp-image-editor.php';
require_once $rootPath . '/public/wordpress/wp-includes/class-wp-image-editor-gd.php';
require_once $rootPath . '/public/wordpress/wp-includes/class-wp-image-editor-imagick.php';

class GCS_Image_Editor_Imagick extends \WP_Image_Editor_Imagick
{
    public function multi_resize($sizes)
    {
        $result = [];
        foreach ($sizes as $size) {
            $result[] = [
                'file'      => wp_basename( $this->file ),
                'width'     => $size['width'],
                'height'    => $size['height'],
                'mime-type' => $this->mime_type
            ];
        }
        return $result;
    }
}

class GCS_Image_Editor_GD extends \WP_Image_Editor_GD
{
    public function multi_resize($sizes)
    {
        $result = [];
        foreach ($sizes as $size) {
            $result[] = [
                'file'      => wp_basename( $this->file ),
                'width'     => $size['width'],
                'height'    => $size['height'],
                'mime-type' => $this->mime_type
            ];
        }
        return $result;
    }
}
