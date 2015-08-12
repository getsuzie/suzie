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

        $enabled = getenv('VARNISH_ENABLED') === 'true' ? true : false;

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
        $headers = [];
        $responses = [];

        if(getenv('VARNISH_TOKEN'))
        {
            $headers['X-Purge-Token'] = getenv('VARNISH_TOKEN');
        }

        if(getenv('VARNISH_AWS'))
        {
            $lb = getenv('VARNISH_AWS_LB');
            $region = getenv('VARNISH_AWS_REGION');

            $output = shell_exec("aws elb describe-load-balancers --load-balancer-name {$lb} --region {$region}");

            $instances = [];

            if(!empty($output))
            {
                $output = json_decode($output);
                foreach($output->LoadBalancerDescriptions[0]->Instances as $instance)
                {
                    $instances[] = $instance->InstanceId;
                }
            }

            if(!empty($instances))
            {
                $output = shell_exec("aws ec2 describe-instances --instance-ids " . implode(' ', $instances) . " --region {$region}");
                if(!empty($output))
                {
                    $output = json_decode($output);
                    foreach($output->Reservations as $reservation)
                    {
                        foreach($reservation->Instances as $instance) {
                            $host = preg_replace('#^https?://#', '', getenv('SITE_URL'));
                            $headers['X-Purge-Url'] = '.*';
                            $headers['X-Purge-Host'] = $host;
                            $headers['X-Purge-Method'] = 'direct';

                            $responses[] = wp_remote_request('http://' . $instance->PublicDnsName, [
                                'method' => 'PURGE',
                                'headers' => $headers,
                            ]);
                        }
                    }
                }
            }
        }
        else
        {
            $headers['X-Purge-Method'] = 'regex';
            $responses[] = wp_remote_request($this->path, [
                'method' => 'PURGE',
                'headers' => $headers,
            ]);
        }
    }
}
