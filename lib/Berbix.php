<?php

namespace Berbix;

define('SDK_VERSION', '0.0.1');
define('CLOCK_DRIFT', 300);

class HTTPClient {
  public function makeRequest($method, $url, $headers, $payload=null) {
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

    if ($method == 'POST') {
      curl_setopt($curl, CURLOPT_POST, true);
    }

    if ($payload != null) {
      $content = json_encode($payload);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
    }

    $json_response = curl_exec($curl);

    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($status < 200 || $status >= 300) {
      throw new \Exception("unexpected status code returned $status, response was $json_response");
    }

    curl_close($curl);

    return json_decode($json_response, true);
  }
}


class Tokens {
  public $refreshToken;
  public $accessToken;
  public $clientToken;
  public $expiry;
  public $transactionId;

  public function __construct($refreshToken, $accessToken=null, $clientToken=null, $expiry=null, $transactionId=null) {
    $this->refreshToken = $refreshToken;
    $this->accessToken = $accessToken;
    $this->clientToken = $clientToken;
    $this->expiry = $expiry;
    $this->transactionId = $transactionId;
  }

  public function refresh($accessToken, $clientToken, $expiry, $transactionId) {
    $this->accessToken = $accessToken;
    $this->clientToken = $clientToken;
    $this->expiry = $expiry;
    $this->transactionId = $transactionId;
  }

  public function needsRefresh() {
    return $this->accessToken == null || $this->expiry == null || $this->expiry < time();
  }

  public static function fromRefresh($refreshToken) {
    return new Tokens($refreshToken);
  }
}


class Client {
  private $clientId;
  private $clientSecret;
  private $apiHost;
  private $httpClient;

  public function __construct($clientId=null, $clientSecret=null, $opts=array()) {
    $this->clientId = $clientId;
    $this->clientSecret = $clientSecret;
    $this->apiHost = $this->getApiHost($opts);

    if (array_key_exists('httpClient', $opts)) {
      $this->httpClient = $opts['httpClient'];
    } else {
      $this->httpClient = new HTTPClient();
    }

    if ($this->clientId == null) {
      throw new \Exception("clientId must be provided");
    }
    if ($this->clientSecret == null) {
      throw new \Exception("clientSecret must be provided");
    }
  }

  private function fetchTokens($path, $payload) {
    $url = $this->apiHost . $path;

    $headers = array(
      "Content-Type: application/json",
      "Authorization: Basic " . base64_encode($this->clientId . ":" . $this->clientSecret),
      "User-Agent: BerbixPHP/" . SDK_VERSION,
    );

    $result = $this->httpClient->makeRequest("POST", $url, $headers, $payload);

    return new Tokens(
      $result['refresh_token'],
      $result['access_token'],
      $result['client_token'],
      $result['expires_in'] + time(),
      $result['user_id']);
  }

  private function getApiHost($opts) {
    if (array_key_exists('apiHost', $opts)) {
      return $opts['apiHost'];
    }

    $env = 'production';
    if (array_key_exists('environment', $opts)) {
      $env = $opts['environment'];
    }

    switch ($env) {
      case 'sandbox':
        return 'https://api.sandbox.berbix.com';
      case 'staging':
        return 'https://api.staging.berbix.com';
      case 'production':
        return 'https://api.berbix.com';
      default:
        throw new \Exception("invalid environment provided");
    }
  }

  public function createTransaction($opts) {
    $payload = array();
    if (array_key_exists('email', $opts)) {
      $payload['email'] = $opts['email'];
    }
    if (array_key_exists('phone', $opts)) {
      $payload['phone'] = $opts['phone'];
    }
    if (array_key_exists('customerUid', $opts)) {
      $payload['customer_uid'] = '' . $opts['customerUid'];
    }
    if (array_key_exists('templateKey', $opts)) {
      $payload['template_key'] = $opts['templateKey'];
    }
    return $this->fetchTokens("/v0/transactions", $payload);
  }

  // This method is deprecated - please use createTransaction instead
  public function createUser($opts) {
    return $this->createTransaction($opts);
  }

  public function refreshTokens($tokens) {
    return $this->fetchTokens("/v0/tokens", array(
      'refresh_token' => $tokens->refreshToken,
      'grant_type' => 'refresh_token',
    ));
  }

  // This method is deprecated - please use createTransaction instead
  public function exchangeCode($code) {
    return $this->fetchTokens("/v0/tokens", array(
      'code' => $code,
      'grant_type' => 'authorization_code',
    ));
  }

  private function refreshIfNecessary($tokens) {
    if ($tokens->needsRefresh()) {
      $refreshed = $this->refreshTokens($tokens);
      $tokens->refresh($refreshed->accessToken, $refreshed->clientToken, $refreshed->expiry, $refreshed->transactionId);
    }
  }

  private function tokenAuthRequest($method, $tokens, $path) {
    $this->refreshIfNecessary($tokens);

    $url = $this->apiHost . $path;

    $headers = array(
      'Content-Type: application/json',
      'Authorization: Token ' . $tokens->accessToken,
      "User-Agent: BerbixPHP/" . SDK_VERSION,
    );

    return $this->httpClient->makeRequest($method, $url, $headers);
  }

  public function fetchTransaction($tokens) {
    return $this->tokenAuthRequest('GET', $tokens, '/v0/transactions');
  }

  // This method is deprecated - please use fetchTransaction instead
  public function fetchUser($tokens) {
    return $this->fetchTransaction($tokens);
  }

  // This method is deprecated - please get clientToken from refresh
  public function createContinuation($tokens) {
    $result = $this->tokenAuthRequest('POST', $tokens, '/v0/continuations');
    return $result['value'];
  }

  public function validateSignature($secret, $body, $header) {
    $parts = explode(',', $header, 3);
    // Version is currently unused in $parts[0]
    $timestamp = $parts[1];
    $signature = $parts[2];
    if (intval($timestamp) < time() - CLOCK_DRIFT) {
      return false;
    }
    $toSign = $timestamp . ',' . $secret . ',' . $body;
    $digest = hash_hmac('sha256', $toSign, $secret);
    return $digest == $signature;
  }
}

?>
