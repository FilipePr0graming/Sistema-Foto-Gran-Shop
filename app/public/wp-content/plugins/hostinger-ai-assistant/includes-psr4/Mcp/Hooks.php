<?php

namespace Hostinger\AiAssistant\Mcp;

use WP_REST_Request;
use WP_REST_Response;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class Hooks {
    public function init(): void {
        add_filter( 'hostinger_once_per_day_events', array( $this, 'limit_triggered_amplitude_events' ) );

        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        if ( ! str_contains( $current_url, HOSTINGER_AI_ASSISTANT_REST_API_BASE . '/mcp' ) ) {
            return;
        }

        add_filter( 'woocommerce_rest_prepare_product_object', array( $this, 'filter_product_meta_fields' ), 10, 3 );
    }

    public function filter_product_meta_fields( WP_REST_Response $response, mixed $post, WP_REST_Request $request ): WP_REST_Response {
        if ( isset( $response->data['meta_data'] ) && is_array( $response->data['meta_data'] ) ) {
            $response->data['meta_data'] = array_values(
                array_filter(
                    $response->data['meta_data'],
                    function ( $meta ) {
                        return ! str_starts_with( $meta->key, '_uag' );
                    }
                )
            );
        }

        return $response;
    }

    public function limit_triggered_amplitude_events( array $events ): array {
        $new_events = array(
            'wordpress.chatbot.survey_filled',
        );

        return array_merge( $events, $new_events );
    }
}
