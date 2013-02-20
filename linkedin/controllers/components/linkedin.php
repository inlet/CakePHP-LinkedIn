<?php
/**
 * --- LinkedIn Component ---
 * Github: https://github.com/inlet/CakePHP-LinkedIn
 * Makes using the LinkedIn API easier
 * Written on top of OAuth vendor component (http://code.42dh.com/oauth/)
 *
 * @author Patrick Brouwer <patrick@inlet.nl>
 */

App::import('Vendor', 'Linkedin.oauth', array('file' => 'OAuth' . DS . 'oauth_consumer.php'));

class LinkedinComponent extends Object {

	// PATH DECLARATIONS
	private $authPath = 'https://api.linkedin.com/';
	private $apiPath = 'http://api.linkedin.com/v1/';
	private $requestToken = 'uas/oauth/requestToken';
  
    /**
    * Permission scope parameters.  Seperate by a space ' '.
    */
	private $scope = 'r_basicprofile r_emailaddress r_contactinfo';
	private $accessToken = 'uas/oauth/accessToken';
	private $authorizeToken = 'uas/oauth/authorize?oauth_token=';
	
	private $sessionRequest = 'linkedin_request_token';
	private $sessionAccess = 'linkedin_access_token';
	
	public $key;
	public $secret;
	private $controller;

	var $components = array('Session');

	/**
	 * Initialize plugin with supplied key and secret
	 *
	 * @param $controller
	 * @param array $settings
	 */
	public function initialize(&$controller, $settings = array()) {
		$this->controller = $controller;
		$this->key = $settings['key'];
		$this->secret = $settings['secret'];
	}

	/**
	 * Connect api and waiting for request..
	 *
	 * @param $redirectUrl (optionally) if not set the default callback 'linkedin_authorize' will be triggered
	 */
	public function connect($redirectUrl = null) {
		if (!isset($redirectUrl)) {
			$redirectUrl = array('controller' => strtolower($this->controller->name), 'action' => 'linkedin_connect_callback');
		}

		$parameters = array();
		if ($this->scope) {
			$parameters['scope'] = $this->scope;
		}
		
		$consumer = $this->_createConsumer();

		// 'POST', $parameters are added by JustAdam: Fix so that you can use member permissions.
		$requestToken = $consumer->getRequestToken($this->authPath . $this->requestToken, Router::url($redirectUrl, true), 'POST', $paramaters);
		$this->Session->write($this->sessionRequest, serialize($requestToken));

		$this->controller->redirect($this->authPath . $this->authorizeToken . $requestToken->key);
	}

	/**
	 * Do authorization..
	 * 
	 * @param null $redirectUrl (optionally) if not set the default callback 'linkedin_connected' will be triggered
	 */
	public function authorize($redirectUrl = null) {
		if (!isset($redirectUrl)) {
			$redirectUrl = array('controller' => strtolower($this->controller->name), 'action' => 'linkedin_authorize_callback');
		}
		
		$requestToken = unserialize($this->Session->read($this->sessionRequest));
		$consumer = $this->_createConsumer();
		$accessToken = $consumer->getAccessToken($this->authPath . $this->accessToken, $requestToken);
		$this->Session->write($this->sessionAccess, serialize($accessToken));
		$this->controller->redirect($redirectUrl);
	}

	/**
	 * API call to GET linkedin data.
	 * 
	 * @param $path
	 * @param $args
	 * @return response
	 */
	public function call($path, $args) {
		$accessToken = unserialize($this->Session->read($this->sessionAccess));
		if ($accessToken === null) {
			trigger_error('Linkedin: accesToken is empty', E_USER_NOTICE);
		}
		$path .= $this->_fieldSelectors($args);
		$consumer = $this->_createConsumer();
		$result = $consumer->get($accessToken->key, $accessToken->secret, $this->apiPath . $path);
		//$responseHeaders = $consumer->getResponseHeader();
		$response = $this->_decode($result);
		if (isset($response['error'])) {
			throw new Exception('Linkedin: '.$response['error']['message']);
		}
		return $response;
	}

	/**
	 * API call to POST data
	 *
	 * @param $path
	 * @param $data  array/object for json or an string for xml/json
	 * @param string $type  "json" or "xml"
	 * @return array|null response
	 */
	public function send($path, $data, $type = 'json') {
		switch ($type) {
			
			case 'json':
				$contentType = 'application/json';
				if (!is_string($data)) {
					$data = json_encode($data);
				}
				break;
				
			case 'xml':
				$contentType = 'text/xml';
				break;
			
			default:
				throw new Exception('Type: "'.$type.'" not supported');
		}		
		$accessToken = $this->Session->read($this->sessionAccess);
		$consumer = $this->_createConsumer();
		$responseText = $consumer->postRaw($accessToken->key, $accessToken->secret, $this->apiPath . $path, $data, $contentType);
		$response = $this->_decode($responseText);
		if (isset($response['error'])) {
			throw new Exception('Linkedin: '.$response['error']['message']);
		}
		return $response;
	}

	/**
	 * Check if is connected with linkedin
	 *
	 * @return bool
	 */
	public function isConnected() {
		$accessToken = $this->Session->read($this->sessionAccess);
		return ($accessToken && is_object($accessToken));
	}

	/**
	 * Create a valid consumer which provides an API
	 * 
	 * @return OAuth_Consumer
	 */
	private function _createConsumer() {
		return new OAuth_Consumer($this->key, $this->secret);
	}

	/**
	 * Decodes the response based on the content type
	 *
	 * @param string $response
	 * @return void
	 * @author Dean Sofer
	 */
	private function _decode($response, $contentType = 'application/xml') {
		// Extract content type from content type header
		if (preg_match('/^([a-z0-9\/\+]+);\s*charset=([a-z0-9\-]+)/i', $contentType, $matches)) {
			$contentType = $matches[1];
			$charset = $matches[2];
		}

		// Decode response according to content type
		switch ($contentType) {
			case 'application/xml':
			case 'application/atom+xml':
			case 'application/rss+xml':
				App::import('Core', 'Xml');
				$Xml = new Xml($response);
				$response = $Xml->toArray(false); // Send false to get separate elements
				$Xml->__destruct();
				$Xml = null;
				unset($Xml);
				break;
			case 'application/json':
			case 'text/javascript':
				$response = json_decode($response, true);
				break;
		}
		return $response;
	}

	/**
	 * Formats an array of fields into the url-friendly nested format
	 *
	 * @param array $fields
	 * @return string $fields
	 * @link http://developer.linkedin.com/docs/DOC-1014
	 */
	private function _fieldSelectors($fields = array()) {
		$result = '';
		if (!empty($fields)) {
			if (is_array($fields)) {
				foreach ($fields as $group => $field) {
					if (is_string($group)) {
						$fields[$group] = $group . $this->_fieldSelectors($field);
					}
				}
				$fields = implode(',', $fields);
			}
			$result .= ':(' . $fields . ')';
		}
		return $result;
	}

}
