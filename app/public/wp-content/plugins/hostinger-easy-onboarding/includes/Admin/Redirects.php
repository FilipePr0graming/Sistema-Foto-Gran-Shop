<?php

namespace Hostinger\EasyOnboarding\Admin;

use Hostinger\EasyOnboarding\Settings;
use Hostinger\WpHelper\Utils as Helper;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Constants;
use Exception;

defined( 'ABSPATH' ) || exit;

class Redirects {

    private string $platform;
    public const PLATFORM_HPANEL  = 'hpanel';
    public const BUILDER_TYPE     = 'prebuilt';
    public const HOMEPAGE_DISPLAY = 'page';

    public function __construct() {
        if ( ! Settings::get_setting( 'first_login_at' ) ) {
            Settings::update_setting( 'first_login_at', gmdate( 'Y-m-d H:i:s' ) );
        }

        if ( isset( $_GET['platform'] ) ) {
            $this->platform = sanitize_text_field( $_GET['platform'] );

            if ( $this->platform === self::PLATFORM_HPANEL ) {
                $this->login_redirect();
            }
        }
    }

    private function login_redirect(): void {
        $is_prebuilt_website = get_option( 'hostinger_builder_type', '' ) === self::BUILDER_TYPE;
        $is_woocommerce_page = in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
        $homepage_id         = ( get_option( 'show_on_front' ) === self::HOMEPAGE_DISPLAY ) ? get_option( 'page_on_front' ) : null;
        $is_gutenberg_page   = $homepage_id ? has_blocks( get_post( $homepage_id )->post_content ) : false;

        add_action(
            'init',
            function () use ( $is_prebuilt_website, $is_woocommerce_page, $homepage_id, $is_gutenberg_page ) {
                if ( $is_prebuilt_website && ! $is_woocommerce_page && $homepage_id && $is_gutenberg_page ) {
                    $redirect_url = get_edit_post_link( $homepage_id, '' );
                } else {
                    $redirect_url = admin_url( 'admin.php?page=hostinger' );
                }

                /**
                 * Fetch experiment data from API and check if new onboarding process is enabled
                 */
                $hostinger_onboarding_completed = get_option( 'hostinger_onboarding_completed', false );
                if ( $hostinger_onboarding_completed === false ) {
                    try {
                        $helper         = new Helper();
                        $config_handler = new Config();
                        $client         = new Client(
                            $config_handler->getConfigValue(
                                'base_rest_uri',
                                Constants::HOSTINGER_REST_URI
                            ),
                            array(
                                Config::TOKEN_HEADER  => $helper::getApiToken(),
                                Config::DOMAIN_HEADER => $helper->getHostInfo(),
                            )
                        );

                        $request = $client->get( '/v3/wordpress/amplitude/experiments', array( 'domain' => $helper->getHostInfo() ) );

                        if ( ! empty( $request['body'] ) ) {
                            $response = json_decode( $request['body'], true );

                            if ( ! empty( $response['data']['wordpress-new-onboarding-process'] ) ) {
                                $redirect_url = admin_url( 'admin.php?page=hostinger&action=onboarding' );
                            }
                        }
                    } catch ( Exception $e ) {
                        error_log( 'Hostinger Onboarding: Failed to fetch experiment data - ' . $e->getMessage() );
                    }
                }

                wp_safe_redirect( $redirect_url );
                exit;
            }
        );
    }
}
