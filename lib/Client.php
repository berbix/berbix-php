<?php

namespace Berbix;

use Exception;

class Client {
  const CLOCK_DRIFT = 300;
  const SDK_VERSION = '1.0.3';

  private $clientSecret;
  private $apiHost;
  private $httpClient;

  public function __construct($apiSecret=null, $opts=array(), $oldOpts=array()) {
    if (is_string($opts)) {
      $apiSecret = $opts;
      $opts = $oldOpts;
    }

    $this->apiSecret = $apiSecret;
    $this->apiHost = $this->getApiHost($opts);

    if (array_key_exists('httpClient', $opts)) {
      $this->httpClient = $opts['httpClient'];
    } else {
      $this->httpClient = new HTTPClient();
    }

    if ($this->apiSecret == null) {
      throw new Exception("apiSecret must be provided");
    }
  }

  private function fetchTokens($path, $payload) {
    $url = $this->apiHost . $path;

    $headers = array(
      "Content-Type: application/json",
      "Authorization: Basic " . base64_encode($this->apiSecret . ":"),
      "User-Agent: BerbixPHP/" . static::SDK_VERSION,
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
        throw new Exception("invalid environment provided");
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

  public function refreshTokens($tokens) {
    return $this->fetchTokens("/v0/tokens", array(
      'refresh_token' => $tokens->refreshToken,
      'grant_type' => 'refresh_token',
    ));
  }

  private function refreshIfNecessary($tokens) {
    if ($tokens->needsRefresh()) {
      $refreshed = $this->refreshTokens($tokens);
      $tokens->refresh($refreshed->accessToken, $refreshed->clientToken, $refreshed->expiry, $refreshed->transactionId);
    }
  }

  private function tokenAuthRequest($method, $tokens, $path, $payload=null) {
    $this->refreshIfNecessary($tokens);

    $url = $this->apiHost . $path;

    $headers = array(
      'Content-Type: application/json',
      'Authorization: Token ' . $tokens->accessToken,
      "User-Agent: BerbixPHP/" . static::SDK_VERSION,
    );
    return $this->httpClient->makeRequest($method, $url, $headers, $payload);
  }

  public function fetchTransaction($tokens) {
    return $this->tokenAuthRequest('GET', $tokens, '/v0/transactions');
  }

  public function deleteTransaction($tokens) {
    $this->tokenAuthRequest('DELETE', $tokens, '/v0/transactions');
  }

  public function updateTransaction($tokens, $params) {
    $payload = array();
    if (array_key_exists('action', $params)) {
      $payload['action'] = $params['action'];
    }
    if (array_key_exists('note', $params)) {
      $payload['note'] = $params['note'];
    }
    $this->tokenAuthRequest('PATCH', $tokens, '/v0/transactions', $payload);
  }

  public function overrideTransaction($tokens, $params) {
    $payload = array();
    if (array_key_exists('responsePayload', $params)) {
      $payload['response_payload'] = $params['responsePayload'];
    }
    if (array_key_exists('flags', $params)) {
      $payload['flags'] = $params['flags'];
    }
    if (array_key_exists('overrideFields', $params)) {
      $payload['override_fields'] = $params['overrideFields'];
    }
    $this->tokenAuthRequest('PATCH', $tokens, '/v0/transactions/override', $payload);
  }

  public function validateSignature($secret, $body, $header) {
    $parts = explode(',', $header, 3);
    // Version is currently unused in $parts[0]
    $timestamp = $parts[1];
    $signature = $parts[2];
    if (intval($timestamp) < time() - static::CLOCK_DRIFT) {
      return false;
    }
    $toSign = $timestamp . ',' . $secret . ',' . $body;
    $digest = hash_hmac('sha256', $toSign, $secret);
    return $digest == $signature;
  }

  // This method is deprecated - please use createTransaction instead
  public function createUser($opts) {
    return $this->createTransaction($opts);
  }
  
  // This method is deprecated - please use createTransaction instead
  public function exchangeCode($code) {
    return $this->fetchTokens("/v0/tokens", array(
      'code' => $code,
      'grant_type' => 'authorization_code',
    ));
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
}
