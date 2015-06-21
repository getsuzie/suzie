<?php

/**
 * Set root path
 */
$rootPath = realpath(__DIR__ . '/..');

/**
 * Include the Composer autoload
 */
include $rootPath . '/vendor/autoload.php';

/**
 * Set URL
 */
$server_url = getenv('SITE_URL');

/**
 * Define in environment
 */
define('APP_ENV',     getenv('APP_ENV'));

/**
 * Set Database Details
 */
define('DB_NAME',     getenv('DB_NAME'));
define('DB_USER',     getenv('DB_USER'));
define('DB_PASSWORD', getenv('DB_PASSWORD'));
define('DB_HOST',     getenv('DB_HOST'));

/**
 * Set debug modes
 */
define('WP_DEBUG', getenv('WP_DEBUG') === 'true'? true: false);
define('WP_LOCAL_DEV', getenv('WP_LOCAL_DEV') === 'true'? true: false);

/**
 * Set custom paths
 * These are required because wordpress is installed in a subdirectory
 */
define('WP_CONTENT_URL', $server_url . '/content');
define('WP_SITEURL',     $server_url . '/wordpress');
define('WP_HOME',        $server_url . '/');
define('WP_CONTENT_DIR', __DIR__ . '/content');


/**
 * Usual Wordpress stuff - Dont overide the ones you have already
 */
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');


/**
 * Authentication Unique Keys and Salts
 * Generate these at: https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service
 */
define('AUTH_KEY',         'Y67764N;I5-:2|DO!GPAP#B>8Ni:bLbI6(`Wuq)!j|H7JG+[Y/-4`+11mAW9XgSn');
define('SECURE_AUTH_KEY',  '2uxhUWw$-kb)qA1.(Y/E2Q>,??2?hqN=_gDXt&X(]JF@U(WPj,]#YZS->,P!t5 p');
define('LOGGED_IN_KEY',    'fB?)5ZaBLP*>!-9;8`em:-1_8_SGy17@-|7{0Q30#rJwtD>Iy11-t|60dx8h<tN^');
define('NONCE_KEY',        'Pu8MkPU?Dytqth*O]hTbh|pg(H6zs|7L[j%OE-WjtGqkb0:p}yYIXZ!<P>fFmHZ:');
define('AUTH_SALT',        'hI2Acz>?PljC#kA:ryzZ]H+>vOy%]|D#2y5Y>A::k|ec5[Mfq#[ Zdh}knZV,7l(');
define('SECURE_AUTH_SALT', 'fV+VLHo5~W|;]0pFCoiLi-{8WhMgUU+Z31|&-6+T`E$vr~~q!S570%=QPz^9?7E[');
define('LOGGED_IN_SALT',   'JVG}(FNqhQA1z3-6zZ%D5o[Kqn+$_0~ja0S_s>$8=#qZcy(w#OMnuS86PVJsOP/q');
define('NONCE_SALT',       'L-A0er+ibw}s03sIp}dGeGu[!##sV_0Gt~OsZy8`K-DW(:_<D1GD?!*77J>h}(V&');


/**
 * WordPress Database Table prefix
 * Use something other than `wp_` for security
 */
$table_prefix  = getenv('DB_PREFIX');

/**
 * WordPress Localized Language, defaults to English.
 */
define('WPLANG', '');


/**
 * Absolute path to the WordPress directory
 */
if (!defined('ABSPATH'))
    define('ABSPATH', dirname(__FILE__) . '/');

/**
 * Suzie
 * use {theme} or {plugin}
 */
define('SUZIE_CDN_THEME', 'theme-name' );
define('SUZIE_CDN_ASSETS', json_encode([

]));

// Sets up WordPress vars and included files
if (defined('DOING_CDN'))
    return;

require_once(ABSPATH . 'wp-settings.php');

suzie();

