<?php

namespace AvsOperatorStatus\Integrations;

/**
 * Class RestApi
 *
 * Handles integrations and modifications to the WordPress REST API.
 */
class RestApi {
    /**
     * Registers all hooks for REST API modifications.
     */
    public function register() {
        add_filter( 'rest_prepare_numerazione', [ $this, 'add_description_to_rest_response' ], 10, 2 );
    }

    /**
     * Appends the term description to the name in REST API responses for the "numerazione" taxonomy.
     *
     * @param \WP_REST_Response $response The response object.
     * @param \WP_Term          $term     The term object.
     * @return \WP_REST_Response The modified response object.
     */
    public function add_description_to_rest_response( $response, $term ): \WP_REST_Response {
        if ( ! empty( $term->description ) ) {
            $response->data['name'] .= ' (' . $term->description . ')';
        }
        return $response;
    }
}
