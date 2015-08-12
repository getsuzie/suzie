<?php

namespace Suzie;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local as Adapter;
use League\Flysystem\MountManager;

/**
 * Class Sync.
 */
class Sync
{
    /**
     * @var \League\Flysystem\MountManager
     */
    protected $manager;

    /**
     * @var string
     */
    protected $rootPath;

    /**
     * @var string
     */
    protected $siteUrl;

    /**
     * @var string
     */
    public $remoteUrl;

    /**
     * @var string
     */
    protected $public;

    /**
     * @var string
     */
    protected $uploads;

    /**
     * Constructor.
     *
     * @param bool $disableHooks
     */
    public function __construct($disableHooks = false)
    {
        $this->setVars();
        $this->configureFlysystem();

        if (!$disableHooks) {
            $this->setAttachmentHooks();
        }
    }

    /**
     *  Sets up some basic variables.
     */
    protected function setVars()
    {
        $this->siteUrl = rtrim(getenv('SITE_URL'), '/');
        $this->rootPath = realpath(__DIR__.'/..');
        $this->public = 'public';
        $this->uploads = '/content/uploads/';
    }

    /**
     * Configure Flysystem to use either S3 or SFTP.
     */
    protected function configureFlysystem()
    {
        if (getenv('SYNC') == 's3') {
            $client = S3Client::factory([
                'key' => getenv('S3_KEY'),
                'secret' => getenv('S3_SECRET'),
                'region' => getenv('S3_REGION'),
            ]);

            $this->remoteUrl = getenv('S3_URL');

            $remoteAdapter = new AwsS3Adapter($client, getenv('S3_BUCKET'));
        } else {
            $client = [
                'host' => getenv('SFTP_HOST'),
                'port' => getenv('SFTP_PORT'),
                'username' => getenv('SFTP_USER'),
                'root' => getenv('SFTP_PATH'),
                'timeout' => 10,
            ];

            if (getenv('SFTP_KEY')) {
                $client['privateKey'] = getenv('SFTP_KEY');
            } else {
                $client['password'] = getenv('SFTP_PASSWORD');
            }

            $this->remoteUrl = getenv('SFTP_URL');
            $remoteAdapter = new SftpAdapter($client);
        }

        $localAdapter = new Adapter($this->rootPath);

        $remote = new Filesystem($remoteAdapter);
        $local = new Filesystem($localAdapter);

        $this->manager = new MountManager([
            'remote' => $remote,
            'local' => $local,
        ]);
    }

    /**
     * Hook into WordPress using various hooks/actions.
     */
    protected function setAttachmentHooks()
    {
        add_action('add_attachment', [$this, 'mediaAdd']);
        add_action('edit_attachment', [$this, 'mediaUpdate']);
        add_action('delete_attachment', [$this, 'mediaDelete']);
        add_filter('attachment_link', [$this, 'attachmentLinkRewrite'], 10, 2);
        add_filter('wp_get_attachment_url', [$this, 'attachmentLinkRewrite'], 10, 2);
        add_filter('wp_generate_attachment_metadata', [$this, 'thumbnailUpload'], 10, 2);
        add_filter('get_attached_file', [$this, 'fetchFileIfNotFound'], 10, 2);
    }

    /**
     * Called by add_attachment then passed of to pushJob
     * Allowing easy implementation of queues in later release.
     *
     * @param $post_id
     */
    public function mediaAdd($post_id)
    {
        $this->pushJob(['type' => 'add', 'id' => $post_id]);
    }

    /**
     * Called by edit_attachment then passed of to pushJob
     * Allowing easy implementation of queues in later release.
     *
     * @param $post_id
     */
    public function mediaUpdate($post_id)
    {
        $this->pushJob(['type' => 'update', 'id' => $post_id]);
    }

    /**
     * Called by delete_attachment then passed of to pushJob
     * Allowing easy implementation of queues in later release.
     *
     * @param $post_id
     */
    public function mediaDelete($post_id)
    {
        $this->pushJob(['type' => 'delete', 'id' => $post_id]);
    }

    /**
     * A single method to handle requests: Add, Update & Delete
     * Allowing easy implementation of queues in later release.
     *
     * @param array $job
     */
    protected function pushJob($job = [])
    {
        $this->handleJob($job);
    }

    /**
     * Call the right handle method based job type.
     *
     * @param $job
     *
     * @return bool
     */
    protected function handleJob($job)
    {
        $method = 'handle'.ucfirst($job['type']);

        if (!method_exists($this, $method)) {
            return false;
        }

        $media = get_post($job['id']);
        $this->{$method}($media);
    }

    /**
     * On add move the local file to remote storage
     * then update the urls in the database.
     *
     * @param $media
     */
    public function handleAdd($media)
    {
        $local = $this->fetchPath($media->guid);
        $remote = $this->fetchRemotePath($local);

        $this->manager->copy("local://{$local}", "remote://{$remote}");

        $localURL = $media->guid;
        $remoteUrl = $this->remoteUrl.$remote;

        global $wpdb;

        $query = $wpdb->prepare(
            "UPDATE $wpdb->posts SET post_content = REPLACE(post_content, %s, %s)",
            $localURL,
            $remoteUrl
        );

        $wpdb->query($query);

        $query = $wpdb->prepare(
            "UPDATE $wpdb->posts SET guid = REPLACE(guid, %s, %s)",
            $localURL,
            $remoteUrl
        );

        $wpdb->query($query);
    }

    /**
     * On update we do nothing for now.
     *
     * @param $media
     */
    public function handleUpdate($media)
    {
        //
    }

    /**
     * On delete we remove the remote file.
     * WordPress will handle the local delete.
     *
     * @param $media
     */
    public function handleDelete($media)
    {
        $metadata = wp_get_attachment_metadata($media->ID);

        list($local, $remote) = $this->getPathsFromGuid($media->guid);

        $this->ifFileThenDelete("remote://{$remote}");

        $remote = $this->removeFileName($remote);

        if (empty($metadata)) {
            return;
        }

        foreach ($metadata['sizes'] as $image) {
            $this->ifFileThenDelete("remote://{$remote}{$image['file']}");
        }
    }

    /**
     * Called by attachment_link and wp_get_attachment_url,
     * we ensure the link uses the remote url.
     *
     * @param $link
     * @param $post_id
     *
     * @return string
     */
    public function attachmentLinkRewrite($link, $post_id)
    {
        $media = get_post($post_id);
        $local = $this->fetchPath($media->guid);
        $remote = $this->fetchRemotePath($local);
        $remoteSet = false;
        $useCdn = false;

        if(getenv('CDN_URL') && getenv('CDN_ENABLED') == 'true' && !is_admin()) {
            $useCdn = true;
        }

        if (strpos($media->guid, $this->remoteUrl) === 0) {
            $remoteSet = true;
        }

        if($useCdn && $remoteSet) {
            return str_replace($this->remoteUrl, getenv('CDN_URL'), $media->guid);
        }

        if($useCdn && !$remoteSet) {
            return getenv('CDN_URL').$remote;
        }

        if(!$useCdn && $remoteSet) {
            return $media->guid;
        }

        return $this->remoteUrl.$remote;
    }

    /**
     * Called by wp_generate_attachment_metadata
     * We upload a copy of each thumbnail to remote.
     *
     * @param $metadata
     * @param $attachment_id
     *
     * @return mixed
     */
    public function thumbnailUpload($metadata, $attachment_id)
    {
        if (empty($metadata)) {
            return $metadata;
        }

        $media = get_post($attachment_id);

        list($local, $remote) = $this->getPathsFromGuid($media->guid, true);

        foreach ($metadata['sizes'] as $image) {
            $localFile = "local://{$local}{$image['file']}";
            $remoteFile = "remote://{$remote}{$image['file']}";

            $this->ifFileThenDelete($remoteFile);

            $this->manager->copy($localFile, $remoteFile);
        }

        return $metadata;
    }

    /**
     * Called by get_attached_file
     * If a file is required by WordPress or Plugin
     * We copy it from remote to local so it can work with it.
     *
     * @param $file
     * @param $attachment_id
     *
     * @return mixed
     */
    public function fetchFileIfNotFound($file, $attachment_id)
    {
        if(!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            return $file;
        }

        $path = explode("/{$this->public}/", $file);
        $path = "/{$this->public}/".$path[1];

        try {
            if (!$this->manager->has("local://{$path}")) {
                $remote = $this->fetchRemotePath($path);
                $this->manager->copy("remote://{$remote}", "local://{$path}");
            }
        } catch (\League\Flysystem\FileNotFoundException $e) {
            // Don't error.
        }

        return $file;
    }

    /**
     * Take a WordPress guid and return path for
     * both local and remote.
     *
     * @param $guid
     * @param bool $removeFileName
     *
     * @return array
     */
    protected function getPathsFromGuid($guid, $removeFileName = false)
    {
        if (strpos($guid, $this->remoteUrl) === 0) {
            $guid = $this->fetchPathFromRemote($guid);
        }

        $local = $this->fetchPath($guid);
        $remote = $this->fetchRemotePath($local);

        if ($removeFileName) {
            $local = $this->removeFileName($local);
            $remote = $this->removeFileName($remote);
        }

        return [$local, $remote];
    }

    /**
     * If file exist then delete it.
     *
     * @param $file
     */
    protected function ifFileThenDelete($file)
    {
        if (!$this->manager->has($file)) {
            return;
        }

        $this->manager->delete($file);
    }

    /**
     * Delete a filename from path.
     *
     * @param $path
     *
     * @return string
     */
    protected function removeFileName($path)
    {
        $parts = explode('/', $path);
        array_pop($parts);

        return implode('/', $parts).'/';
    }

    /**
     * Return a local path from remote url.
     *
     * @param $url
     *
     * @return mixed
     */
    protected function fetchPathFromRemote($url)
    {
        return str_replace($this->remoteUrl, $this->siteUrl . "/", $url);
    }

    /**
     * Return a local path from local url.
     *
     * @param $url
     *
     * @return mixed
     */
    protected function fetchPath($url)
    {
        return str_replace($this->siteUrl, $this->public, $url);
    }

    /**
     * Return a remote path from local path.
     *
     * @param $path
     *
     * @return mixed
     */
    protected function fetchRemotePath($path)
    {
        return str_replace($this->public . '/', '', $path);
    }

    /**
     * Moves local file to remote.
     *
     * @param $url
     * @param $path
     * @return void
     */
    public function moveToRemote($url, $path = false)
    {
        if($path)
        {
            $local = $this->fetchPath($url);
        }
        else
        {
            $local = $url;
            $parts = explode("/{$this->public}/", $local);

            if (isset($parts[1]))
            {
                $local = "/{$this->public}/".$parts[1];
            }
        }

        $remote = str_replace($this->public, '', $local);

        $this->ifFileThenDelete("remote://{$remote}");
        $this->manager->copy("local://{$local}", "remote://{$remote}");
    }

    /**
     * Checks if remote file exists
     *
     * @param $url
     * @param $path
     *
     * @return void
     */
    public function hasRemoteFile($url, $path = false)
    {
        if ($path)
        {
            $local = $this->fetchPath($url);
        }
        else
        {
            $local = $url;
            $parts = explode("/{$this->public}/", $local);

            if (isset($parts[1]))
            {
                $local = "/{$this->public}/".$parts[1];
            }
        }

        $remote = str_replace($this->public, '', $local);

        if (!$this->manager->has("remote://{$remote}"))
        {
            return false;
        }

        $this->manager->copy("remote://{$remote}", "local://{$local}");
        return true;
    }
}
