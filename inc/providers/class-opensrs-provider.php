<?php
/**
 * OpenSRS Service Provider
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel\Providers;

use Reseller_Panel\Abstract_Classes\Base_Service_Provider;
use Reseller_Panel\Interfaces\Domain_Importer_Interface;

/**
 * OpenSRS Provider Class
 */
class OpenSRS_Provider extends Base_Service_Provider implements Domain_Importer_Interface {

	const TEST_ENDPOINT = 'https://horizon.opensrs.net:55443';
	const LIVE_ENDPOINT = 'https://rr-n1-tor.opensrs.net:55443';
	const MAX_RESPONSE_BODY_LENGTH = 500;
	const MAX_XML_PREVIEW_LENGTH = 200;

	/**
	 * Provider key
	 *
	 * @var string
	 */
	protected $key = 'opensrs';

	/**
	 * Provider name
	 *
	 * @var string
	 */
	protected $name = 'OpenSRS (TuCows)';

	/**
	 * Supported services
	 *
	 * @var array
	 */
	protected $supported_services = array( 'domains' );

	/**
	 * Get configuration fields
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array(
			'api_key' => array(
				'label' => __( 'API Key', 'ultimate-multisite' ),
				'type' => 'text',
				'description' => __( 'Your OpenSRS API key (also known as Private Key)', 'ultimate-multisite' ),
				'link' => 'https://manage.opensrs.com/account/settings/api',
				'link_text' => __( 'Get API Key', 'ultimate-multisite' ),
			),
			'username' => array(
				'label' => __( 'Reseller Username', 'ultimate-multisite' ),
				'type' => 'text',
				'description' => __( 'Your OpenSRS reseller username', 'ultimate-multisite' ),
			),
			'environment' => array(
				'label' => __( 'Environment', 'ultimate-multisite' ),
				'type' => 'select',
				'options' => array(
					'test' => __( 'Sandbox (Test)', 'ultimate-multisite' ),
					'live' => __( 'Production (Live)', 'ultimate-multisite' ),
				),
				'default' => 'test',
				'description' => __( 'Use Sandbox for testing, Production for live transactions', 'ultimate-multisite' ),
			),
			'domain_fee' => array(
				'label' => __( 'Domain Markup Fee (Optional)', 'ultimate-multisite' ),
				'type' => 'text',
				'description' => __( 'Additional fee or markup to add to all domain prices (e.g., 2.50 for $2.50 per domain). Leave empty for no markup.', 'ultimate-multisite' ),
			),
		);
	}

	/**
	 * Test connection to OpenSRS
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
				__( 'OpenSRS is not configured. Please enter your API credentials above and save the settings before testing the connection.', 'ultimate-multisite' ) 
			);
		}

		try {
			$environment = $this->get_config_value( 'environment', 'test' );
			$endpoint = 'live' === $environment ? self::LIVE_ENDPOINT : self::TEST_ENDPOINT;
			
			$response = $this->make_request( 'ACCOUNT', 'GET_BALANCE' );

			if ( is_wp_error( $response ) ) {
				// Add more context to the error message
				$error_message = $response->get_error_message();
				$error_code = $response->get_error_code();
				$error_data = $response->get_error_data();
				
				// Enhance error data with additional context
				if ( ! is_array( $error_data ) ) {
					$error_data = array();
				}
				
				if ( ! isset( $error_data['endpoint'] ) ) {
					$error_data['endpoint'] = $endpoint;
				}
				if ( ! isset( $error_data['environment'] ) ) {
					$error_data['environment'] = $environment;
				}
				
				// Provide helpful hints for common errors
				if ( strpos( $error_message, 'cURL error' ) !== false ) {
					$error_message .= ' - Please check your server\'s network connectivity and SSL certificate configuration.';
				} elseif ( $error_code === 'missing_credentials' ) {
					$error_message .= ' - Both API Key and Username are required.';
				} elseif ( strpos( $error_message, 'Authentication failed' ) !== false || strpos( $error_message, 'Invalid signature' ) !== false ) {
					$error_message .= ' - Please verify your API key and username are correct.';
				}
				
				return new \WP_Error( $error_code, $error_message, $error_data );
			}

			if ( isset( $response['is_success'] ) && 1 === (int) $response['is_success'] ) {
				return true;
			}

			// Extract more detailed error information from response
			$error_text = isset( $response['response_text'] ) ? $response['response_text'] : __( 'Connection failed', 'ultimate-multisite' );
			$error_code = isset( $response['response_code'] ) ? $response['response_code'] : 'unknown';
			
			$error_data = array(
				'response_code' => $error_code,
				'endpoint' => $endpoint,
				'environment' => $environment,
			);
			
			if ( $error_code ) {
				$error_text .= ' (Code: ' . $error_code . ')';
			}

			return new \WP_Error( 'api_error', $error_text, $error_data );
		} catch ( \Exception $e ) {
			return new \WP_Error( 
				'exception', 
				sprintf( 
					__( 'Exception occurred: %s. Please verify your API credentials and try again.', 'ultimate-multisite' ), 
					$e->getMessage() 
				),
				array(
					'exception_class' => get_class( $e ),
					'exception_file' => $e->getFile(),
					'exception_line' => $e->getLine(),
				)
			);
		}
	}

	/**
	 * Make API request to OpenSRS
	 *
	 * @param string $object OpenSRS object
	 * @param string $action OpenSRS action
	 * @param array  $attributes Request attributes
	 *
	 * @return array|WP_Error
	 */
	private function make_request( $object, $action, $attributes = array() ) {
		$this->load_config();

		$username = $this->get_config_value( 'username' );
		$api_key = $this->get_config_value( 'api_key' );
		$environment = $this->get_config_value( 'environment', 'test' );

		if ( ! $username || ! $api_key ) {
			$error = new \WP_Error( 'missing_credentials', __( 'OpenSRS credentials are incomplete', 'ultimate-multisite' ) );
			\Reseller_Panel\Logger::log_error( 'OpenSRS', $error->get_error_message(), array( 'action' => $action, 'object' => $object ) );
			return $error;
		}

		$endpoint = 'live' === $environment ? self::LIVE_ENDPOINT : self::TEST_ENDPOINT;

		// Log API call
		\Reseller_Panel\Logger::log_api_call(
			'OpenSRS',
			sprintf( '%s/%s', $object, $action ),
			'POST',
			array(
				'environment' => $environment,
				'endpoint' => $endpoint,
				'attributes_count' => count( $attributes ),
			)
		);

		// Build XML request
		$xml = $this->build_xml_request( $object, $action, $attributes, $username, $api_key );

		// Calculate signature for authentication
		$signature = $this->calculate_signature( $xml, $api_key );

		// Make HTTP request with authentication headers
		$response = wp_remote_post(
			$endpoint,
			array(
				'body' => $xml,
				'timeout' => 30,
				'sslverify' => true,
				'headers' => array(
					'Content-Type' => 'text/xml',
					'X-Username' => $username,
					'X-Signature' => $signature,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			// Log error
			\Reseller_Panel\Logger::log_error(
				'OpenSRS',
				$response->get_error_message(),
				array(
					'action' => $action,
					'object' => $object,
					'error_code' => $response->get_error_code(),
				)
			);

			// Add detailed diagnostic information to the error
			$error_message = $response->get_error_message();
			$error_code = $response->get_error_code();
			
			return new \WP_Error(
				$error_code,
				$error_message,
				array(
					'endpoint' => $endpoint,
					'action' => $action,
					'object' => $object,
					'environment' => $environment,
				)
			);
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		// Log response
		\Reseller_Panel\Logger::log_info(
			'OpenSRS',
			sprintf( '%s/%s - Response received', $object, $action ),
			array(
				'http_code' => $http_code,
				'response_length' => strlen( $body ),
			)
		);

		// Check for HTTP errors
		if ( $http_code !== 200 ) {
			$error = new \WP_Error(
				'http_error',
				sprintf( __( 'HTTP error %d received from OpenSRS API', 'ultimate-multisite' ), $http_code ),
				array(
					'http_code' => $http_code,
					'response_body' => substr( $body, 0, self::MAX_RESPONSE_BODY_LENGTH ),
					'endpoint' => $endpoint,
				)
			);
			\Reseller_Panel\Logger::log_error( 'OpenSRS', $error->get_error_message(), array( 'action' => $action, 'http_code' => $http_code ) );
			return $error;
		}

		$parsed = $this->parse_xml_response( $body );

		// Log parsing errors
		if ( is_wp_error( $parsed ) ) {
			\Reseller_Panel\Logger::log_error( 'OpenSRS', $parsed->get_error_message(), array( 'action' => $action ) );
		}

		return $parsed;
	}

	/**
	 * Calculate signature for OpenSRS authentication
	 *
	 * @param string $xml XML request body
	 * @param string $api_key API key
	 *
	 * @return string MD5 signature
	 */
	private function calculate_signature( $xml, $api_key ) {
		return md5( md5( $xml . $api_key ) . $api_key );
	}

	/**
	 * Build XML request
	 *
	 * @param string $object OpenSRS object
	 * @param string $action OpenSRS action
	 * @param array  $attributes Request attributes
	 * @param string $username Reseller username
	 * @param string $api_key API key
	 *
	 * @return string
	 */
	private function build_xml_request( $object, $action, $attributes, $username, $api_key ) {
		$attributes_xml = '';
		foreach ( $attributes as $key => $value ) {
			$attributes_xml .= sprintf( '<item key="%s">%s</item>', esc_attr( $key ), esc_xml( $value ) );
		}

		$xml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no" ?>
<!DOCTYPE OPS_envelope SYSTEM "ops.dtd">
<OPS_envelope>
	<header>
		<version>0.9</version>
	</header>
	<body>
		<data_block>
			<dt_assoc>
				<item key="protocol">XCP</item>
				<item key="action">$action</item>
				<item key="object">$object</item>
				$attributes_xml
			</dt_assoc>
		</data_block>
	</body>
</OPS_envelope>
XML;

		return $xml;
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
			return new \WP_Error( 'empty_response', __( 'Empty response from OpenSRS', 'ultimate-multisite' ) );
		}

		libxml_use_internal_errors( true );
		$doc = new \DOMDocument();

		if ( ! $doc->loadXML( $xml ) ) {
			$errors = libxml_get_errors();
			$error_messages = array();
			foreach ( $errors as $error ) {
				$error_messages[] = trim( $error->message );
			}
			libxml_clear_errors();
			
			return new \WP_Error(
				'invalid_xml',
				__( 'Invalid XML response from OpenSRS', 'ultimate-multisite' ),
				array(
					'xml_errors' => implode( '; ', $error_messages ),
					'response_preview' => substr( $xml, 0, self::MAX_XML_PREVIEW_LENGTH ),
				)
			);
		}

		$xpath = new \DOMXPath( $doc );

		// Get success status
		$is_success_node = $xpath->query( '//item[@key="is_success"]' );
		$is_success = $is_success_node->length > 0 ? (int) $is_success_node[0]->nodeValue : 0;

		// Get response code
		$response_code_node = $xpath->query( '//item[@key="response_code"]' );
		$response_code = $response_code_node->length > 0 ? $response_code_node[0]->nodeValue : '';

		// Get response text
		$response_text_node = $xpath->query( '//item[@key="response_text"]' );
		$response_text = $response_text_node->length > 0 ? $response_text_node[0]->nodeValue : '';

		$result = array(
			'is_success' => $is_success,
			'response_code' => $response_code,
			'response_text' => $response_text,
		);

		// If not successful, add as error data for better debugging
		if ( ! $is_success && $response_text ) {
			$result['error_details'] = array(
				'response_code' => $response_code,
				'response_text' => $response_text,
			);
		}

		// Extract nested data structures (for GET_TLDLIST and similar responses)
		// Look for dt_array and dt_assoc structures
		$this->extract_nested_xml_data( $xpath, $result );

		return $result;
	}

	/**
	 * Extract nested XML data from OpenSRS response
	 *
	 * Handles dt_array and dt_assoc structures
	 *
	 * @param DOMXPath $xpath XPath instance
	 * @param array    &$result Reference to result array to populate
	 *
	 * @return void
	 */
	private function extract_nested_xml_data( $xpath, &$result ) {
		// Get all data items with keys
		$items = $xpath->query( '//item[@key]' );

		foreach ( $items as $item ) {
			$key = $item->getAttribute( 'key' );
			
			// Skip already processed items
			if ( in_array( $key, array( 'is_success', 'response_code', 'response_text' ), true ) ) {
				continue;
			}

			// Check if this item contains nested data (dt_array or dt_assoc)
			$dt_array = $item->getElementsByTagName( 'dt_array' )->length > 0;
			$dt_assoc = $item->getElementsByTagName( 'dt_assoc' )->length > 0;

			if ( $dt_array ) {
				// Parse dt_array (indexed array)
				$result[ $key ] = $this->parse_dt_array( $item );
			} elseif ( $dt_assoc ) {
				// Parse dt_assoc (associative array)
				$result[ $key ] = $this->parse_dt_assoc( $item );
			} else {
				// Simple text value
				$result[ $key ] = $item->nodeValue;
			}
		}
	}

	/**
	 * Parse dt_array from OpenSRS XML
	 *
	 * @param DOMElement $element The dt_array element
	 *
	 * @return array
	 */
	private function parse_dt_array( $element ) {
		$result = array();
		$dt_array = $element->getElementsByTagName( 'dt_array' )->item( 0 );

		if ( ! $dt_array ) {
			return $result;
		}

		foreach ( $dt_array->childNodes as $child ) {
			if ( $child->nodeType !== XML_ELEMENT_NODE ) {
				continue;
			}

			$key = $child->getAttribute( 'key' );
			
			if ( $child->getElementsByTagName( 'dt_array' )->length > 0 ) {
				$result[ $key ] = $this->parse_dt_array( $child );
			} elseif ( $child->getElementsByTagName( 'dt_assoc' )->length > 0 ) {
				$result[ $key ] = $this->parse_dt_assoc( $child );
			} else {
				$result[ $key ] = $child->nodeValue;
			}
		}

		return $result;
	}

	/**
	 * Parse dt_assoc from OpenSRS XML
	 *
	 * @param DOMElement $element The dt_assoc element or parent with dt_assoc child
	 *
	 * @return array
	 */
	private function parse_dt_assoc( $element ) {
		$result = array();
		$dt_assoc = $element->getElementsByTagName( 'dt_assoc' )->item( 0 );

		if ( ! $dt_assoc ) {
			return $result;
		}

		foreach ( $dt_assoc->childNodes as $child ) {
			if ( $child->nodeType !== XML_ELEMENT_NODE ) {
				continue;
			}

			$key = $child->getAttribute( 'key' );
			
			if ( $child->getElementsByTagName( 'dt_array' )->length > 0 ) {
				$result[ $key ] = $this->parse_dt_array( $child );
			} elseif ( $child->getElementsByTagName( 'dt_assoc' )->length > 0 ) {
				$result[ $key ] = $this->parse_dt_assoc( $child );
			} else {
				$result[ $key ] = $child->nodeValue;
			}
		}

		return $result;
	}

	/**
	 * Get TLD list from OpenSRS
	 *
	 * @return array|WP_Error
	 */
	public function get_tld_list() {
		$this->load_config();

		$response = $this->make_request( 'DOMAIN', 'GET_TLDLIST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Parse TLD list from response and return
		return isset( $response['tld_list'] ) ? $response['tld_list'] : array();
	}

	/**
	 * Sync pricing to Ultimate Multisite products
	 *
	 * @return bool|WP_Error
	 */
	public function sync_pricing() {
		$this->load_config();

		// Get TLD list
		$tlds = $this->get_tld_list();

		if ( is_wp_error( $tlds ) ) {
			return $tlds;
		}

		// Create/update products in UMS for each TLD
		foreach ( $tlds as $tld => $prices ) {
			$this->create_or_update_product( $tld, $prices );
		}

		return true;
	}

	/**
	 * Create or update product in UMS
	 *
	 * @param string $tld TLD
	 * @param array  $prices Price data
	 *
	 * @return bool
	 */
	private function create_or_update_product( $tld, $prices ) {
		// This will integrate with UMS Product API
		// For now, just return true
		return true;
	}

	/**
	 * Get available domains from OpenSRS (Domain_Importer_Interface implementation)
	 *
	 * Fetches the list of available domains and their pricing from the OpenSRS reseller account.
	 *
	 * @return array|WP_Error Array of domains on success, WP_Error on failure
	 */
	public function get_domains() {
		$this->load_config();

		if ( ! $this->is_configured() ) {
			return new \WP_Error( 'not_configured', __( 'OpenSRS is not configured', 'ultimate-multisite' ) );
		}

		// Get TLD list from OpenSRS
		$response = $this->make_request( 'DOMAIN', 'GET_TLDLIST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Extract and format domain list
		$domains = array();

		// Get domain markup fee if configured
		$domain_fee = floatval( $this->get_config_value( 'domain_fee', 0 ) );

		if ( isset( $response['tld_list'] ) && is_array( $response['tld_list'] ) ) {
			foreach ( $response['tld_list'] as $tld => $data ) {
				// Get base prices
				$registration_price = isset( $data['registration_price'] ) ? floatval( $data['registration_price'] ) : 0;
				$renewal_price = isset( $data['renewal_price'] ) ? floatval( $data['renewal_price'] ) : 0;
				$transfer_price = isset( $data['transfer_price'] ) ? floatval( $data['transfer_price'] ) : 0;

				// Apply domain fee to all prices
				$registration_price = $registration_price + $domain_fee;
				$renewal_price = $renewal_price + $domain_fee;
				$transfer_price = $transfer_price + $domain_fee;

				$domains[] = array(
					'tld'                 => sanitize_key( $tld ),
					'name'                => '.' . sanitize_text_field( $tld ),
					'price'               => $registration_price, // Use registration price as the base
					'registration_price'  => $registration_price,
					'renewal_price'       => $renewal_price,
					'transfer_price'      => $transfer_price,
				);
			}
		}

		return $domains;
	}
}
