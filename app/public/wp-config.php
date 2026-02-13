<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

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
define( 'AUTH_KEY',          'hWH<,AhzX?-(>hnNc?  o;w5l(uZM6hF{!>zzE&WuX-LdPl9J:t_$tF=%tL1Gz+V' );
define( 'SECURE_AUTH_KEY',   'pYI{/^gggOMPZl WZr3Fro2^(n1by_#*3}r/_(7Hx{1F>B*e7#L.1|n@i_x>6?(@' );
define( 'LOGGED_IN_KEY',     'O^&c. ORB8]&Ra:peLv(y<%A3=#}:KNOKesa>|PchK#pEKC_$TN%eSEZ6CQ{vi;[' );
define( 'NONCE_KEY',         'p[bsDx1Hrl6zb9=M$WhF|#A:ppVMg$u$My%9lx]I(_l~7hNE;h6X<9A~<vuScx[R' );
define( 'AUTH_SALT',         'hyk_?l;[5ZC)eAh~q:.pvGN[N-j3N?:,R>QCNNW#U@ccP4ObI%`[sY?=[My4WOSk' );
define( 'SECURE_AUTH_SALT',  'kU9fd:=W2T?I@@j2#/;E`o@M=f1:S17B!?KeC-vuP~vX^7kI9`9g6T*}EL)8#Ji<' );
define( 'LOGGED_IN_SALT',    '|#|F?Ey]> j/mSgntoeY~$;[umZkiQ&PDTdzxU}LC|<8 xCg+:AN{#uvl4SaB3Sq' );
define( 'NONCE_SALT',        'q>&,p?7mx^Z>p}[.3WPGL[]<>U*r+4NT:DW;*A<X$zy7v? f:x+t!R,eD{W*79|&' );
define( 'WP_CACHE_KEY_SALT', '#G/n/!SM!k$d8;ivu0KyOg?gHBg8Evt 8Z]0L|aN91=O{k.g|{%^jgt[skPz=|s8' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



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
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
