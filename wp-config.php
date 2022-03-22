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
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

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
define( 'AUTH_KEY',         'z45L@xk+u-MN!^1UOy4~-tcw|6#/?a0%.gdV)d!ja_?+l#50h$U=$I2spEypoEvB' );
define( 'SECURE_AUTH_KEY',  'qj$e>KrQO*q<l><GiXMjMQ)5mubGnZ1 yaOD&;]A$0B0Hkghxx}VXt|X+9hCFC._' );
define( 'LOGGED_IN_KEY',    '!.|sX0zm G~fD[ w&KBYv;O}A762vN)ejie[GL_#;`1?Q_A)U)pFzPre*|VXFlaO' );
define( 'NONCE_KEY',        '=Uf0`)AySM%Wh hPk*$:OGHxQU+6]:qyZP.._iHT]!$!g}[$q%37&?{!~C>49C!O' );
define( 'AUTH_SALT',        'E%,:FI6>i`H.xY.cwN?7!9HC}U#vtx92Z`f*!KSzQ:~U(O5/mBPSU.Kb(sHYgVTJ' );
define( 'SECURE_AUTH_SALT', 'Z(X^^Z>O}Hc$ Jp-A41dSJfQip4b_ZwU) cS>.U.[YA5%}=>=mv6L.;P75Z=;whF' );
define( 'LOGGED_IN_SALT',   'Rk}tS|afeN`%CZ3fJ;:[g2/?DTnZUG+4;C[P4nynEQqN&SgDP_Ah$Em#WjS?cjd`' );
define( 'NONCE_SALT',       '0lR1gB*WQC=%{xOy_Z9Lj3p,sX$DykkBW+II0aLEw(uYS|qh&XVE[cJu1Z9buK{n' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
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
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
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
define( 'WP_ALLOW_REPAIR', true );