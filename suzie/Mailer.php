<?php

namespace Suzie;

use Mailgun\Mailgun;

/**
 * Class Mailer.
 */
class Mailer
{
    /**
     * @var Mailgun
     */
    protected $mailgun;

    /**
     * @var string
     */
    protected $domain;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->mailgun = new Mailgun(getenv('MAILGUN_KEY'));
        $this->domain = getenv('MAILGUN_DOMAIN');
    }

    /**
     * Sends email.
     *
     * @param $args
     * @param $attachments
     *
     * @throws \Mailgun\Messages\Exceptions\MissingRequiredMIMEParameters
     */
    public function send($args, $attachments)
    {
        if (getenv('MAILGUN_FROM')) {
            $args['from'] = getenv('MAILGUN_FROM');
        }

        try {
            return $this->mailgun->sendMessage($this->domain, $args, $attachments);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Runs original WordPress logical to build up arguments.
     *
     * @param $to
     * @param $subject
     * @param $message
     * @param $headers
     * @param $attachments
     *
     * @return array
     */
    public function fetchArguments($to, $subject, $message, $headers, $attachments)
    {
        $cc = [];
        $bcc = [];

        if (empty($headers)) {
            $headers = array();
        } else {
            if (!is_array($headers)) {
                // Explode the headers out, so this function can take both
                // string headers and an array of headers.
                $tempheaders = explode("\n", str_replace("\r\n", "\n", $headers));
            } else {
                $tempheaders = $headers;
            }

            $headers = array();
            $cc = array();
            $bcc = array();

            // If it's actually got contents
            if (!empty($tempheaders)) {
                // Iterate through the raw headers
                foreach ((array) $tempheaders as $header) {
                    if (strpos($header, ':') === false) {
                        if (false !== stripos($header, 'boundary=')) {
                            $parts = preg_split('/boundary=/i', trim($header));
                            $boundary = trim(str_replace(array("'", '"'), '', $parts[1]));
                        }

                        continue;
                    }

                    // Explode them out
                    list($name, $content) = explode(':', trim($header), 2);

                    // Cleanup crew
                    $name = trim($name);
                    $content = trim($content);

                    switch (strtolower($name)) {
                        // Mainly for legacy -- process a From: header if it's there
                        case 'from':
                            if (strpos($content, '<') !== false) {
                                // So... making my life hard again?
                                $from_name = substr($content, 0, strpos($content, '<') - 1);
                                $from_name = str_replace('"', '', $from_name);
                                $from_name = trim($from_name);

                                $from_email = substr($content, strpos($content, '<') + 1);
                                $from_email = str_replace('>', '', $from_email);
                                $from_email = trim($from_email);
                            } else {
                                $from_email = trim($content);
                            }

                            break;
                        case 'content-type':
                            if (strpos($content, ';') !== false) {
                                list($type, $charset) = explode(';', $content);
                                $content_type = trim($type);

                                if (false !== stripos($charset, 'charset=')) {
                                    $charset = trim(str_replace(array('charset=', '"'), '', $charset));
                                } elseif (false !== stripos($charset, 'boundary=')) {
                                    $boundary = trim(str_replace(array('BOUNDARY=', 'boundary=', '"'), '', $charset));
                                    $charset = '';
                                }
                            } else {
                                $content_type = trim($content);
                            }

                            break;
                        case 'cc':
                            $cc = array_merge((array) $cc, explode(',', $content));

                            break;
                        case 'bcc':
                            $bcc = array_merge((array) $bcc, explode(',', $content));

                            break;
                        default:
                            // Add it to our grand headers array
                            $headers[trim($name)] = trim($content);

                            break;
                    }
                }
            }
        }

        if (!isset($from_name)) {
            $from_name = 'WordPress';
        }

        if (!isset($from_email)) {
            // Get the site domain and get rid of www.
            $sitename = strtolower($_SERVER['SERVER_NAME']);

            if (substr($sitename, 0, 4) == 'www.') {
                $sitename = substr($sitename, 4);
            }

            $from_email = 'wordpress@'.$sitename;
        }

        return [[
            'from' => $from_email,
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'html' => $message,
            'cc' => $cc,
            'bcc' => $bcc,
        ], [
            'attachments' => $attachments,
        ]];
    }

    /**
     * Handle the logic for wp_mail().
     */
    public static function boot()
    {
        $suzieMailer = new self();
        list($args, $attachments) = call_user_func_array([$suzieMailer, 'fetchArguments'], func_get_args());
        return $suzieMailer->send($args, $attachments);
    }
}
