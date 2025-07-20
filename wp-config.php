<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'quicklearn' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'T=F=Mc/T`$HC2!1q.x@ r[Wu6}]#35(rX$f5=G_3|?pBRMHS|4Di6n3BCwXd6]%s' );
define( 'SECURE_AUTH_KEY',  '4U7x:Z^muh,eCPgKe__Jx}Y$oEt4?n#Dzbqu2ak6o;gz>jHK*RjKnh|0FUjyR@Tr' );
define( 'LOGGED_IN_KEY',    '7d?1SfwW*h|#ktQ{4{Jt~}W<MSQ$>goMM#75%X~Cdv)wLI%+DUfM<3tvP{kFXabd' );
define( 'NONCE_KEY',        'Y6]Isy,7vXJ5[ICY/K*mtXWQh[Rja<&{h}41&RPP,8oGmH^@ngG{R,:]$2f)ii[G' );
define( 'AUTH_SALT',        'k=#I|:vBCm@j,eotuP,c|U1 XV((;u&gPKu<InM.q|y:mvYkq![#rEAYDuN^Um,v' );
define( 'SECURE_AUTH_SALT', '.$)u@qg~4!]>d3%,FU=:^bg>e.`C,29KW59DSNr;^K^n_]OASd#B_N!.lcp.+I,}' );
define( 'LOGGED_IN_SALT',   'BR^A15RU#-+:.9,#!-s#y|;aqHSYw]uvAqx]I]=I`=[*4fQHIp>96>5Ofp&FHN#w' );
define( 'NONCE_SALT',       '4LGqGG;])d(rDHZdDz,stEn<p(#%#/h@JRFn3j:}/^,~))r3&xF9UO[LeH.5m]Jj' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
