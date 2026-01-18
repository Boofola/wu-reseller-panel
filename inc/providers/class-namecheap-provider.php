<?php
/**
 * NameCheap Service Provider
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel\Providers;

use Reseller_Panel\Abstract_Classes\Base_Service_Provider;

/**
 * NameCheap Provider Class
 */
class NameCheap_Provider extends Base_Service_Provider {

	const TEST_ENDPOINT = 'https://api.sandbox.namecheap.com/api/xml.response';
	const LIVE_ENDPOINT = 'https://api.namecheap.com/api/xml.response';

	/**
	 * Provider key
	 *
	 * @var string
	 */
	protected $key = 'namecheap';

	/**
	 * Provider name
	 *
	 * @var string
	 */
	protected $name = 'NameCheap';

	/**
	 * Supported services
	 *
	 * @var array
	 */
	protected $supported_services = array( 'domains', 'ssl', 'hosting', 'emails' );

	/**
	 * Get configuration fields
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array(
			'api_user' => array(
				'label' => __( 'API User', 'ultimate-multisite' ),
				'type' => 'text',
				'description' => __( 'Your NameCheap API user (username)', 'ultimate-multisite' ),
				'link' => 'https://www.namecheap.com/support/api/intro/',
				'link_text' => __( 'API Documentation', 'ultimate-multisite' ),
			),
			'api_key' => array(
				'label' => __( 'API Key', 'ultimate-multisite' ),
				'type' => 'password',
				'description' => __( 'Your NameCheap API key', 'ultimate-multisite' ),
			),
			'username' => array(
				'label' => __( 'Username', 'ultimate-multisite' ),
				'type' => 'text',
				'description' => __( 'Your NameCheap account username', 'ultimate-multisite' ),
			),
			'client_ip' => array(
				'label' => __( 'Client IP (Optional)', 'ultimate-multisite' ),
				'type' => 'text',
				'description' => __( 'If API is restricted to specific IPs, enter your server IP', 'ultimate-multisite' ),
			),
			'environment' => array(
				'label' => __( 'Environment', 'ultimate-multisite' ),
				'type' => 'select',
				'options' => array(
					'sandbox' => __( 'Sandbox (Test)', 'ultimate-multisite' ),
					'live' => __( 'Production (Live)', 'ultimate-multisite' ),
				),
				'default' => 'sandbox',
				'description' => __( 'Use Sandbox for testing, Production for live transactions', 'ultimate-multisite' ),
			),
			'base_domain_price' => array(
				'label' => __( 'Base Domain Price (per year)', 'ultimate-multisite' ),
				'type' => 'text',
				'description' => __( 'Admin-defined base price for domains (can be overridden per TLD)', 'ultimate-multisite' ),
			),
			'base_ssl_price' => array(
				'label' => __( 'Base SSL Price (per year)', 'ultimate-multisite' ),
				'type' => 'text',
				'description' => __( 'Admin-defined base price for SSL certificates', 'ultimate-multisite' ),
			),
		);
	}

	/**
	 * Test connection to NameCheap
	 *
	 * @return bool|WP_Error
	 */
	public function test_connection() {
		// Check if DOM extension is available first
		if ( ! class_exists( '\\DOMDocument' ) && ! extension_loaded( 'dom' ) ) {
			return new \WP_Error( 
				'missing_extension', 
				__( 'PHP DOM extension is required but not enabled on this server. Please contact your hosting provider to enable the DOM extension.', 'ultimate-multisite' ) 
			);
		}

		$this->load_config();

		if ( ! $this->is_configured() ) {
			return new \WP_Error( 
				'not_configured', 
				__( 'NameCheap is not configured. Please enter your API credentials above and save the settings before testing the connection.', 'ultimate-multisite' ) 
			);
		}

		try {
			$response = $this->make_request(
				'namecheap.users.getBalance',
				array()
			);

			if ( is_wp_error( $response ) ) {
				// Add more context to the error message
				$error_message = $response->get_error_message();
				$error_code = $response->get_error_code();
				
				// Provide helpful hints for common errors
				if ( strpos( $error_message, 'cURL error' ) !== false ) {
					$error_message .= ' - Please check your server\'s network connectivity and SSL certificate configuration.';
				} elseif ( $error_code === 'missing_credentials' ) {
					$error_message .= ' - API User, API Key, and Username are all required.';
				} elseif ( strpos( $error_message, 'Invalid request IP' ) !== false ) {
					$error_message .= ' - Your server IP may not be whitelisted in your NameCheap account. Please add it in your NameCheap API settings.';
				} elseif ( strpos( $error_message, 'API key is invalid' ) !== false ) {
					$error_message .= ' - Please verify your API credentials are correct.';
				}
				
				return new \WP_Error( $error_code, $error_message );
			}

			if ( isset( $response['ApiResponse']['UserGetBalanceResult']['AccountBalance'] ) ) {
				return true;
			}

			return new \WP_Error( 
				'api_error', 
				__( 'Connection failed. The API returned an unexpected response. Please verify your credentials and try again.', 'ultimate-multisite' ) 
			);
		} catch ( \Exception $e ) {
			return new \WP_Error( 
				'exception', 
				sprintf( 
					__( 'Exception occurred: %s. Please verify your API credentials and try again.', 'ultimate-multisite' ), 
					$e->getMessage() 
				)
			);
		}
	}

	/**
	 * Make API request to NameCheap
	 *
	 * @param string $command API command
	 * @param array  $params Request parameters
	 *
	 * @return array|WP_Error
	 */
	private function make_request( $command, $params = array() ) {
		$this->load_config();

		$api_user = $this->get_config_value( 'api_user' );
		$api_key = $this->get_config_value( 'api_key' );
		$username = $this->get_config_value( 'username' );
		$environment = $this->get_config_value( 'environment', 'sandbox' );
		$client_ip = $this->get_config_value( 'client_ip', '' );

		if ( ! $api_user || ! $api_key || ! $username ) {
			return new \WP_Error( 'missing_credentials', __( 'NameCheap credentials are incomplete', 'ultimate-multisite' ) );
		}

		$endpoint = 'live' === $environment ? self::LIVE_ENDPOINT : self::TEST_ENDPOINT;

		// Build request parameters
		$request_params = array(
			'ApiUser' => $api_user,
			'ApiKey' => $api_key,
			'UserName' => $username,
			'Command' => $command,
			'ClientIp' => $client_ip ?: $this->get_server_ip(),
		);

		// Merge with provided parameters
		$request_params = array_merge( $request_params, $params );

		// Make HTTP request
		$response = wp_remote_get(
			add_query_arg( $request_params, $endpoint ),
			array(
				'timeout' => 30,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );

		return $this->parse_xml_response( $body );
	}

	/**
	 * Get server IP address
	 *
	 * @return string
	 */
	private function get_server_ip() {
		$ip = '127.0.0.1';

		if ( ! empty( $_SERVER['SERVER_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) );
		} elseif ( ! empty( $_SERVER['LOCAL_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['LOCAL_ADDR'] ) );
		}

		return $ip;
	}

	/**
	 * Parse XML response
	 *
	 * @param string $xml XML response
	 *
	 * @return array|WP_Error
	 */
	private function parse_xml_response( $xml ) {
		if ( empty( $xml ) ) {
			return new \WP_Error( 'empty_response', __( 'Empty response from NameCheap', 'ultimate-multisite' ) );
		}

		libxml_use_internal_errors( true );
		$doc = new \DOMDocument();

		if ( ! $doc->loadXML( $xml ) ) {
			return new \WP_Error( 'invalid_xml', __( 'Invalid XML response', 'ultimate-multisite' ) );
		}

		$xpath = new \DOMXPath( $doc );

		// Check for API errors
		$errors = $xpath->query( '//ApiResponse/Errors/Error' );
		if ( $errors->length > 0 ) {
			$error_text = '';
			foreach ( $errors as $error ) {
				$error_text .= $error->nodeValue . '; ';
			}
			return new \WP_Error( 'api_error', trim( $error_text ) );
		}

		// Convert XML to array
		return $this->xml_to_array( $doc->documentElement );
	}

	/**
	 * Convert XML element to array
	 *
	 * @param \DOMElement $element XML element
	 *
	 * @return array
	 */
	private function xml_to_array( $element ) {
		$result = array();

		// Get attributes
		if ( $element->hasAttributes() ) {
			foreach ( $element->attributes as $attr ) {
				$result[ '@' . $attr->name ] = $attr->value;
			}
		}

		// Get child elements
		if ( $element->hasChildNodes() ) {
			$children = array();
			foreach ( $element->childNodes as $child ) {
				if ( $child->nodeType === XML_ELEMENT_NODE ) {
					$name = $child->nodeName;
					$value = $this->xml_to_array( $child );

					if ( isset( $children[ $name ] ) ) {
						if ( ! is_array( $children[ $name ] ) || ! isset( $children[ $name ][0] ) ) {
							$children[ $name ] = array( $children[ $name ] );
						}
						$children[ $name ][] = $value;
					} else {
						$children[ $name ] = $value;
					}
				} elseif ( $child->nodeType === XML_TEXT_NODE ) {
					$text = trim( $child->nodeValue );
					if ( ! empty( $text ) ) {
						if ( empty( $children ) ) {
							return $text;
						}
						$children['_text'] = $text;
					}
				}
			}
			$result = array_merge( $result, $children );
		}

		return $result;
	}

	/**
	 * Get domain pricing from NameCheap
	 *
	 * @param string $tld TLD to check
	 *
	 * @return array|WP_Error
	 */
	public function get_domain_pricing( $tld ) {
		$response = $this->make_request(
			'namecheap.domains.getTldList',
			array(
				'searchTerm' => $tld,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Get SSL pricing from NameCheap
	 *
	 * @return array|WP_Error
	 */
	public function get_ssl_pricing() {
		$response = $this->make_request(
			'namecheap.ssl.getTldList',
			array()
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Sync pricing to Ultimate Multisite products
	 *
	 * @return bool|WP_Error
	 */
	public function sync_pricing() {
		$this->load_config();

		// Sync domain pricing
		$domain_response = $this->make_request(
			'namecheap.domains.getTldList',
			array()
		);

		if ( is_wp_error( $domain_response ) ) {
			return $domain_response;
		}

		// Create/update products in UMS for each TLD
		if ( isset( $domain_response['ApiResponse']['TldListResult']['Tld'] ) ) {
			$tlds = $domain_response['ApiResponse']['TldListResult']['Tld'];

			if ( ! is_array( $tlds ) || ! isset( $tlds[0] ) ) {
				$tlds = array( $tlds );
			}

			foreach ( $tlds as $tld_data ) {
				$this->create_or_update_product( 'domain', $tld_data );
			}
		}

		return true;
	}

	/**
	 * Create or update product in UMS
	 *
	 * @param string $service_type Service type (domain, ssl, hosting, emails)
	 * @param array  $product_data Product data
	 *
	 * @return bool
	 */
	private function create_or_update_product( $service_type, $product_data ) {
		// This will integrate with UMS Product API
		// Admin-defined base prices can override NameCheap pricing
		return true;
	}
}