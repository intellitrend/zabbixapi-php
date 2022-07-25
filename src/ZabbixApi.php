<?php
/**
  * Zabbix PHP API Client (using the JSON-RPC Zabbix API)
  *
  * @version 3.1.0
  * @author Wolfgang Alper <wolfgang.alper@intellitrend.de>
  * @copyright IntelliTrend GmbH, http://www.intellitrend.de
  * @license GNU Lesser General Public License v3.0
  *
  * You can redistribute this library and/or modify it under the terms of
  * the GNU LGPL as published by the Free Software Foundation,
  * either version 3 of the License, or any later version.
  * However you must not change author and copyright information.
  *
  * Implementation based on the offical Zabbix API docs.
  * Tested on Linux and Windows.
  *
  * Requires PHP 5.6+, JSON functions, CURL, Zabbix 3.0+
  * For usage see examples provided in 'examples/'
  *
  * Errorhandling:
  * Errors are handled by exceptions.
  * - In case of ZabbxiApi errors, the msg and code is passed to the exception class ZabbixApiException
  * - In case of generic API errors, the code passed is defined by the constants: ZabbixApi::EXCEPTION_CLASS_CODE
  * - In case of session specfic API errors, the code passed is defined by the constants: ZabbixApi::EXCEPTION_CLASS_CODE_SESSION
  */


namespace IntelliTrend\Zabbix;

class ZabbixApiException extends \Exception {
    public function __construct($message, $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

class ZabbixApi {

	const VERSION = "3.1.0";

	const EXCEPTION_CLASS_CODE = 1000;
	const EXCEPTION_CLASS_CODE_SESSION = 2000;
	const SESSION_PREFIX = 'zbx_';

	protected $zabUrl = '';
	protected $zabUser = '';
	protected $zabPassword = '';
	protected $authKey = '';
	protected $debug = false;
	protected $sessionDir = '';	// directory where to store the crypted session
	protected $sessionFileName = ''; // fileName of crypted session, depends on zabUrl/zabUser and SESSION_PREFIX
	protected $sessionFile = ''; // directory + / + fileName
	protected $sslCaFile = ''; // set external CA bundle. If not set use php.ini default settings. See https://curl.haxx.se/docs/caextract.html
	protected $sslVerifyPeer = 1; // verify cert
	protected $sslVerifyHost = 2;  // if cert is valid, check hostname
	protected $useGzip = true;
	protected $timeout = 30; //max. time in seconds to process request
	protected $connectTimeout = 10; //max. time in seconds to connect to server
	protected $authKeyIsValid = false; // whether the autkey was actually successfully used in this session
	protected $authKeyIsToken = false; // whether the autkey is a token and therefore not bound to a session
	protected $zabApiVersion = ''; // Zabbix API version. Updated when calling getApiVersion() or on first _login() attempt. Needed for API change in 5.4 (user -> username)


	/**
	 * Constructor
	 * Check for required Curl module
	 * @throws Exception $e
	 */
	public function __construct() {
		if (!function_exists('curl_init')) {
			throw new ZabbixApiException("Missing Curl support. Install the PHP Curl module.", ZabbixApi::EXCEPTION_CLASS_CODE);
		}
	}


	/**
	 * Login - setup internal structure and validate sessionDir
	 *
	 * @param string $zabUrl - Zabbix base URL
	 * @param string $zabUser - Zabbix user name
	 * @param string $zabPassword - Zabbix password
	 * @param array $options - optional settings. Example: array('sessionDir' => '/tmp', 'sslVerifyPeer' => true, 'useGzip' => true, 'debug' => true);
	 * @throws Exception $e
	 */
	public function login($zabUrl, $zabUser, $zabPassword, $options = array()) {

		$zabUrl = substr($zabUrl , -1) == '/' ? $zabUrl :  $zabUrl .= '/';
		$this->zabUrl = $zabUrl;

		$this->zabUser = $zabUser;
		$this->zabPassword = $zabPassword;

		$this->applyOptions($options);

		// if sessionDir is passed as param check if a directory exists. otherwise use the default temp directory
		if (array_key_exists('sessionDir', $options)) {
			$sessionDir = $options['sessionDir'];
			if (!is_dir($sessionDir)) {
				throw new ZabbixApiException("Error - sessionDir:$sessionDir is not a valid directory", ZabbixApi::EXCEPTION_CLASS_CODE);
			}
			if (!is_writable($sessionDir)) {
				throw new ZabbixApiException("Error - sessionDir:$sessionDir is not a writeable directory", ZabbixApi::EXCEPTION_CLASS_CODE);
			}
			$this->sessionDir = $sessionDir;
		}
		else {
			$this->sessionDir = sys_get_temp_dir();
		}

		$sessionFileName = ZabbixApi::SESSION_PREFIX. md5($this->zabUrl . $this->zabUser);
		$this->sessionFileName = $sessionFileName;
		$this->sessionFile = $this->sessionDir. '/'. $this->sessionFileName;

		if ($this->debug) {
			print "DBG login(). Using sessionDir:$this->sessionDir, sessionFileName:$this->sessionFileName\n";
		}

		$sessionAuthKey = $this->readAuthKeyFromSession();

		// When debug is enabled, we want to see if the session has been reused. This requires a call to the Zabbix-API.
		if ($this->debug) {
			$this->call('user.get', array('output' => 'userid', 'limit' => 1));
			if ($this->authKey == $sessionAuthKey) {
				print "DBG login(). Re-Using existing session\n";
			} else {
				print "DBG login(). Creating new session\n";
			}
		}
	}

	/**
	 * Login - setup internal structure via API token
	 *
	 * @param string $zabUrl - Zabbix base URL
	 * @param string $zabToken - Zabbix API token
	 * @param array $options - optional settings. Example: array('sessionDir' => '/tmp', 'sslVerifyPeer' => true, 'useGzip' => true, 'debug' => true);
	 * @throws Exception $e
	 */
	public function loginToken($zabUrl, $zabToken, $options = array()) {

		$zabUrl = substr($zabUrl , -1) == '/' ? $zabUrl :  $zabUrl .= '/';
		$this->zabUrl = $zabUrl;

		// login fields are unused with tokens
		$this->zabUser = '';
		$this->zabPassword = '';

		$this->applyOptions($options);

		// session files are also not required
		$this->sessionDir = '';
		$this->sessionFileName = '';
		$this->sessionFile = '';

		// use token directly as auth key
		$this->authKey = $zabToken;
		$this->authKeyIsValid = true;
		$this->authKeyIsToken = true;
	}


	/**
	 * Convenient function to get remote API version
	 *
	 * @return string $apiVersion
	 */
	public function getApiVersion() {
		$this->zabApiVersion = $this->callZabbixApi('apiinfo.version');
		return $this->zabApiVersion;
	}


	/**
	 * Get version of this library
	 *
	 * @return string $version
	 */
	public function getVersion() {
		return Zabbixapi::VERSION;
	}


	/**
	 * Get authKey used for API communication - Supportfunction, not used internally
	 * If there was no call to the Zabbix-API before, this function will call the Zabbix-API
	 * to ensure a valid $authKey.
	 * @return string $authKey
	 */
	public function getAuthKey() {
		// if there was no login to the Zabbix-API so far, we do not know wether the key is valid
		if (!$this->authKeyIsValid && !$this->authKeyIsToken) {
			// Simple call that requires Authentication - will update the key if needed.
			$this->call('user.get', array('output' => 'userid', 'limit' => 1));
		}
		return $this->authKey;
	}


	/**
	 * Enable / Disable debug
	 *
	 * @param boolean $status. True = enable
	 */
	public function setDebug($status) {
		$this->debug = $status && 1;
	}


	/**
	 * Logout from Zabbix Server and delete the session from filesystem
	 *
	 * Only use this method if its really needed, because you cannot reuse the session later on.
	 */
	public function logout() {
		if ($this->debug) {
			print "DBG logout(). Delete sessionFile and logout from Zabbix\n";
		}
		
		// token-based sessions can't logout
		if ($this->authKeyIsToken) {
			return;
		}

		$response = $this->callZabbixApi('user.logout');
		// remove session locally - ignore if session no longer exists
		$ret = unlink($this->getSessionFile());
	}


	/**
	 * Get session directory
	 *
	 * @return string $sessionDir
	 */
	public function getSessionDir() {
		return $this->sessionDir;
	}

	/**
	 * Get session FileName without path
	 *
	 * @return string $sessionFileName
	 */
	public function getSessionFileName() {
		return $this->sessionFileName;
	}


	/**
	 * Get full FileName with path
	 *
	 * @return string $sessionFile
	 */
	public function getSessionFile() {
		return $this->sessionFile;
	}


	/**
	 * High level Zabbix Api call. Will automatically re-login and retry if call failed using the current authKey.
	 *
	 * Note: Can only be called after login() was called once before at any time.
	 *
	 * @param string $method. Zabbix API method i.e. 'host.get'
	 * @param mixed $params. Params as defined in the Zabbix API for that particular method
	 * @return mixed $response. Decoded Json response or scalar
	 * @throws Exception
	 */
	public function call($method, $params = array()) {
		if (!$this->zabUrl) {
			throw new ZabbixApiException("Missing Zabbix URL.", ZabbixApi::EXCEPTION_CLASS_CODE);
		}

		// for token-based auth, pass through to callZabbixApi
		if ($this->authKeyIsToken) {
			return $this->callZabbixApi($method, $params);
		}

		// for classic login, try to call API with existing auth first
		try {
			return $this->callZabbixApi($method, $params);
		}
		catch (ZabbixApiException $e) {
			// check for session exception
			if ($e->getCode() == ZabbixApi::EXCEPTION_CLASS_CODE_SESSION) {
				// renew session and retry call
				$this->__login();
				return $this->callZabbixApi($method, $params);
			} else {
				// re-throw any other exception
				throw $e;
			}
		}

		// technically unreachable, but just in case
		return NULL;
	}


	/*************** Protected / Private  functions ***************/


	/**
	 * Internal login function to perform the login. Saves authKey to sessionFile on success.
	 *
	 * @return boolean $success
	 */
	protected function __login() {
		// Try to login to our API
		if ($this->debug) {
			print "DBG __login(). Called\n";
		}
		// Zabbix version 5.4 changed key 'user' -> 'username'. So need to check API version upfront
		if (!$this->zabApiVersion) {
			// sets automatically $this->zabApiVersion
			$this->getApiVersion();
		}
		if (version_compare($this->zabApiVersion, '5.4.0') < 0) {
			$userKey = 'user';
		} else {
			$userKey = 'username';
		}
		$response = $this->callZabbixApi('user.login', array( 'password' => $this->zabPassword, $userKey => $this->zabUser));

		if (isset($response) && strlen($response) == 32) {
			$this->authKey = $response;
			//on successful login save authKey to session
			$this->writeAuthKeyToSession();
			$this->authKeyIsValid = true;
			return true;
		}

		// login failed
		$this->authKey = '';
		$this->authKeyIsValid = false;
		return false;
	}


	/**
	 * Internal call to Zabbix API via RPC/API call
	 *
	 * @param string $method
	 * @param mixed $params
	 * @return mixed $response. Json decoded response
	 * @throws Exception
	 */
	protected function callZabbixApi($method, $params = array()) {

		if (!$this->authKey && $method != 'user.login' && $method != 'apiinfo.version') {
			throw new ZabbixApiException("Not logged in and no authKey", ZabbixApi::EXCEPTION_CLASS_CODE_SESSION);
		}

		$request = $this->buildRequest($method, $params);
		$rawResponse = $this->executeRequest($this->zabUrl.'api_jsonrpc.php', $request);

		if ($this->debug) {
			print "DBG callZabbixApi(). Raw response from API: $rawResponse\n";
		}
		$response = json_decode($rawResponse, true);

		if ( isset($response['id']) && $response['id'] == 1 && isset($response['result']) ) {
			$this->authKeyIsValid = true;
			return $response['result'];
		}

		if (is_array($response) && array_key_exists('error', $response)) {
			$code = $response['error']['code'];
			$message = $response['error']['message'];
			$data = $response['error']['data'];
			$msg = "$message [$data]";
			throw new ZabbixApiException($msg, $code);
		}

		$msg = "Error without further information.";
		throw new ZabbixApiException($msg);

	}


	/**
	 * Build the Zabbix JSON-RPC request
	 *
	 * @param string $method
	 * @param mixed $params
	 * @return string $request. Json encoded request object
	 * @throws Exception
	 */
	protected function buildRequest($method, $params = array()) {
		if ($params && !is_array($params)) {
			throw new ZabbixApiException("Params passed to API call must be an array", ZabbixApi::EXCEPTION_CLASS_CODE);
		}

		$request = array(
			'auth' => $this->authKey,
			'method' => $method,
			'id' => 1,  // since we do not work in parallel, always using the same id should work
			'params' => ( is_array($params) ? $params : array() ),
			'jsonrpc' => "2.0"
		);

		if ($method == 'user.login') {
			unset($request['auth']);
		}

		if ($method == 'apiinfo.version') {
			unset($request['auth']);
		}

		return json_encode($request);
	}


	/**
	 * Low level execute the request
	 *
	 * @param string $zabUrl. Url pointing to API endpoint
	 * @param mixed $data.
	 * @return string $response. Json encoded response from API
	 */
	protected function executeRequest($zabUrl, $data = '') {
		$c = curl_init($zabUrl);
		// These are required for submitting JSON-RPC requests

		$headers = array();
		$headers[]  = 'Content-Type: application/json-rpc';
		$headers[]  = 'User-Agent: IntelliTrend/ZabbixApi;Version:'. Zabbixapi::VERSION;

		$opts = array(
			// allow to return a curl handle
			CURLOPT_RETURNTRANSFER => true,
			// max number of seconds to allow curl to process the request
			CURLOPT_TIMEOUT => $this->timeout,
			// max number of seconds to establish a connection
			CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
			// ensure the certificate itself is valid (signed by a trusted CA, the certificate chain is complete, etc.)
			CURLOPT_SSL_VERIFYPEER => $this->sslVerifyPeer,
			// 0 or 2. Ensure the host connecting to is the host named in the certificate.
			CURLOPT_SSL_VERIFYHOST => $this->sslVerifyHost,
			// follow if url has changed
			CURLOPT_FOLLOWLOCATION => true,
			// no cached connection or responses
			CURLOPT_FRESH_CONNECT => true
		);


		$opts[CURLOPT_HTTPHEADER] = $headers;

		$opts[CURLOPT_CUSTOMREQUEST] = "POST";
		$opts[CURLOPT_POSTFIELDS] = ( is_array($data) ? http_build_query($data) : $data );

		// use compression
		$opts[CURLOPT_ENCODING] = 'gzip';

		if ($this->debug) {
			print "DBG executeRequest(). CURL Params: ". $opts[CURLOPT_POSTFIELDS]. "\n";
		}

		curl_setopt_array($c, $opts);
		// pass CAs if set
		if ($this->sslCaFile != '') {
			curl_setopt($c, CURLOPT_CAINFO, $this->sslCaFile);
		}

		$response = @curl_exec($c);
		$info = curl_getinfo($c);
		$sslErrorMsg = curl_error($c);

		$httpCode = $info['http_code'];
		$sslVerifyResult = $info['ssl_verify_result'];

		if ( $httpCode == 0 || $httpCode >= 400) {
			throw new ZabbixApiException("Request failed with HTTP-Code:$httpCode, sslVerifyResult:$sslVerifyResult. $sslErrorMsg", $httpCode);
		}


		if ( $sslVerifyResult != 0 && $this->sslVerifyPeer == 1) {
			$error = curl_error($c);
			throw new ZabbixApiException("Request failed with SSL-Verify-Result:$sslVerifyResult. $sslErrorMsg", $sslVerifyResult);
		}

		curl_close($c);
		return $response;
	}


	/**
	 * Read encrypted authKey from session-file, decrpyt and save it in the class instance
	 *
	 * @return string authKey. Empty string '' if authKey was not found.
	 */
	protected function readAuthKeyFromSession() {
		$sessionFile = $this->getSessionFile();

		// if no session exist simply return
		$fh = @fopen($sessionFile, "r");
		if ($fh == false) {
			if ($this->debug) {
				print "DBG readAuthKeyFromSession(). sessionFile not found. sessionFile:$sessionFile\n";
			}
			return '';
		}

		$encryptedKey = fread($fh, filesize($sessionFile));
		if (!$encryptedKey) {
			return '';
		}

		fclose($fh);

		$authKey = $this->decryptAuthKey($encryptedKey);

		if (!$authKey) {
			if ($this->debug) {
				print "DBG readAuthKeyFromSession(). Decrypting authKey from sessionFile failed. sessionFile:$sessionFile\n";
			}
			$this->authKey = '';
			return NULL;
		}


		if ($this->debug) {
			print "DBG readAuthKeyFromSession(). Read authKey:$authKey from sessionFile:$sessionFile\n";
		}

		// save to class instance
		$this->authKey = $authKey;
		return $authKey;
	}


	/**
	 * Write authKey encrypted to the session-file
	 *
	 * @return true
	 * @throws exeception
	 */
	protected function writeAuthKeyToSession() {
		//write content
		$sessionFile = $this->getSessionFile();

		$fh = fopen($sessionFile, "w");
		if ($fh == false) {
			throw new ZabbixApiException("Cannot open sessionFile. sessionFile:$sessionFile", ZabbixApi::EXCEPTION_CLASS_CODE);
		}

		$encryptedKey = $this->encryptAuthKey($this->authKey);

		if (fwrite($fh, $encryptedKey) == false) {
			throw new ZabbixApiException("Cannot write encrypted authKey to sessionFile. sessionFile:$sessionFile", ZabbixApi::EXCEPTION_CLASS_CODE);
		}

		fclose($fh);

		if ($this->debug) {
			print "DBG writeAuthKeyToSession(). Saved encrypted authKey:$encryptedKey to sessionFile:$sessionFile\n";
		}

		return true;
	}


	/**
	 * Encrypt authKey
	 *
	 * @param string $authKey (plain)
	 * @return string $encryptedKey
	 */
	protected function encryptAuthKey($authKey) {
		$encryptedAuthKey = base64_encode(openssl_encrypt(
			$authKey,
			"aes-128-cbc",
			hash("SHA256", $this->zabUser. $this->zabPassword, true),
			OPENSSL_RAW_DATA,
			"1356647968472110"
		));

		return $encryptedAuthKey;
	}


	/**
	 * Decrypt authKey
	 *
	 * @param string $encryptedAuthKey
	 * @return string $authKey. If decryption fails key is empty ""
	 */
	protected function decryptAuthKey($encryptedAuthKey) {
		$authKey = openssl_decrypt(base64_decode($encryptedAuthKey),
			"aes-128-cbc",
			hash("SHA256", $this->zabUser. $this->zabPassword, true),
			OPENSSL_RAW_DATA,
			"1356647968472110"
		);

		return $authKey;
	}

	/**
	 * Internal function to apply options from an associative array.
	 */
	protected function applyOptions($options) {
		$validOptions = array('debug', 'sessionDir', 'sslCaFile', 'sslVerifyHost', 'sslVerifyPeer', 'useGzip', 'timeout', 'connectTimeout');
		foreach ($options as $k => $v) {
			if (!in_array($k, $validOptions)) {
				throw new ZabbixApiException("Invalid option used. option:$k", ZabbixApi::EXCEPTION_CLASS_CODE);
			}
		}


		if (array_key_exists('debug', $options)) {
			$this->debug = $options['debug'] && true;
		}

		if ($this->debug) {
			print "DBG login(). Using zabUser:$zabUser, zabUrl:$zabUrl\n";
			print "DBG login(). Library Version:". ZabbixApi::VERSION. "\n";
		}

		if (array_key_exists('sslCaFile', $options)) {
			if (!is_file($options['sslCaFile'])) {
				throw new ZabbixApiException("Error - sslCaFile:". $options['sslCaFile']. " is not a valid file", ZabbixApi::EXCEPTION_CLASS_CODE);
			}
			$this->sslCaFile = $options['sslCaFile'];
		}

		if (array_key_exists('sslVerifyPeer', $options)) {
			$this->sslVerifyPeer = ($options['sslVerifyPeer']) ? 1 : 0;
		}

		if (array_key_exists('sslVerifyHost', $options)) {
			$this->sslVerifyHost = ($options['sslVerifyHost']) ? 2 : 0;
		}

		if (array_key_exists('useGzip', $options)) {
			$this->useGzip = ($options['useGzip']) ? true : false;
		}

		if (array_key_exists('timeout', $options)) {
			$this->timeout = (intval($options['timeout']) > 0)? $options['timeout'] : 30;
		}

		if (array_key_exists('connectTimeout', $options)) {
			$this->timeout = (intval($options['connectTimeout']) > 0)? $options['connectTimeout'] : 30;
		}

		if ($this->debug) {
			print "DBG login(). Using sslVerifyPeer:". $this->sslVerifyPeer . " sslVerifyHost:". $this->sslVerifyHost. " useGzip:". $this->useGzip. " timeout:". $this->timeout. " connectTimeout:". $this->connectTimeout. "\n";
		}
	}
}