<?php
/**
 * Set root path.
 */
$rootPath = realpath(__DIR__ . '/..');

/**
 * Include the Composer autoloader.
 */
include $rootPath . '/vendor/autoload.php';

/**
 * Set site URL.
 */
$server_url = getenv('SITE_URL');

/**
 * Define environment.
 */
define('APP_ENV', getenv('APP_ENV'));

/**
 * Set database details.
 */
define('DB_NAME',     getenv('DB_NAME'));
define('DB_USER',     getenv('DB_USER'));
define('DB_PASSWORD', getenv('DB_PASSWORD'));
define('DB_HOST',     getenv('DB_HOST'));

/**
 * Set debug mode.
 */
define('WP_DEBUG', getenv('WP_DEBUG') === 'true' ? true : false);

/**
 * SSL.
 */
define('FORCE_SSL_ADMIN', getenv('WP_FORCE_SSL') === 'true' ? true : false);
define('FORCE_SSL_LOGIN', getenv('WP_FORCE_SSL') === 'true' ? true : false);

if ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
{
    $_SERVER['HTTPS'] = 'on';
}

/**
 * Set custom paths.
 * These are required because WordPress is installed in a subdirectory.
 */
define('WP_CONTENT_URL', $server_url . '/content');
define('WP_SITEURL',     $server_url . '/wordpress');
define('WP_HOME',        $server_url . '/');
define('WP_CONTENT_DIR', __DIR__ . '/content');

/**
 * Usual Wordpress stuff - don't overide the ones you have already.
 */
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

/**
 * Authentication unique keys and salts.
 *
 * @link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service
 */
define('AUTH_KEY',         'put your unique phrase here');
define('SECURE_AUTH_KEY',  'put your unique phrase here');
define('LOGGED_IN_KEY',    'put your unique phrase here');
define('NONCE_KEY',        'put your unique phrase here');
define('AUTH_SALT',        'put your unique phrase here');
define('SECURE_AUTH_SALT', 'put your unique phrase here');
define('LOGGED_IN_SALT',   'put your unique phrase here');
define('NONCE_SALT',       'put your unique phrase here');

/**
 * WordPress database table prefix.
 * Use something other than `wp_` for security.
 */
$table_prefix = getenv('DB_PREFIX');

/**
 * Absolute path to the WordPress directory.
 */
if (!defined('ABSPATH'))
{
    define('ABSPATH', dirname(__FILE__) . '/');
}

/**
 * Suzie settings.
 * Use {theme} or {plugin} for directory paths.
 */

// Set theme name for CDN.
define('SUZIE_CDN_THEME', 'theme-name');

// Assets to send to CDN.
define('SUZIE_CDN_ASSETS', json_encode([

]));

// Folders to send to CDN.
define('SUZIE_CDN_FOLDERS', json_encode([

]));

// Sets up WordPress vars and included files.
if (defined('DOING_CDN'))
{
    return;
}

require_once(ABSPATH . 'wp-settings.php');

suzie();
