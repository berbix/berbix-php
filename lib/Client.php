<?php

namespace Berbix;

use Exception;

class Client {
  const CLOCK_DRIFT = 300;
  const SDK_VERSION = '2.0.2';

  private $clientSecret;
  private $apiHost;
  private $httpClient;

  public function __construct(string $apiSecret=null, array $opts=array()) {
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

  private function fetchTokens(string $path, array $payload): Tokens {
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
      $result['transaction_id'],
      $result);
  }

  private function getApiHost(array $opts): string {
    if (array_key_exists('apiHost', $opts)) {
      return $opts['apiHost'];
    }

    return 'https://api.berbix.com';
  }

  private function parseCreateTransactionOptions(array $opts, array $payload): array {
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
    return $payload;
  }

  public function createTransaction(array $opts): Tokens {
    $payload = array();
    $payload = $this->parseCreateTransactionOptions($opts, $payload);
    return $this->fetchTokens("/v0/transactions", $payload);
  }

  public function createHostedTransaction(array $opts): array {
    $payload = array();
    $payload = $this->parseCreateTransactionOptions($opts, $payload);
    $payload['hosted_options'] = (object)[];
    if (array_key_exists('completionEmail', $opts)) {
      $payload['hosted_options']['completion_email'] = $opts['completionEmail'];
    }
    if (array_key_exists('redirectUrl', $opts)) {
      $payload['hosted_options']['redirect_url'] = $opts['redirectUrl'];
    }
    $result = $this->fetchTokens("/v0/transactions", $payload);
    return array(
      "tokens" => $result,
      "hostedUrl" => $result->response['hosted_url'],
    );
  }

  public function refreshTokens(Tokens $tokens): Tokens {
    return $this->fetchTokens("/v0/tokens", array(
      'refresh_token' => $tokens->refreshToken,
      'grant_type' => 'refresh_token',
    ));
  }

  private function refreshIfNecessary(Tokens $tokens): void {
    if ($tokens->needsRefresh()) {
      $refreshed = $this->refreshTokens($tokens);
      $tokens->refresh($refreshed->accessToken, $refreshed->clientToken, $refreshed->expiry, $refreshed->transactionId);
    }
  }

  private function tokenAuthRequest(string $method, Tokens $tokens, string $path, array $payload=null) {
    $this->refreshIfNecessary($tokens);

    $url = $this->apiHost . $path;

    $headers = array(
      'Content-Type: application/json',
      'Authorization: Token ' . $tokens->accessToken,
      "User-Agent: BerbixPHP/" . static::SDK_VERSION,
    );
    return $this->httpClient->makeRequest($method, $url, $headers, $payload);
  }

  public function fetchTransaction(Tokens $tokens): array {
    return $this->tokenAuthRequest('GET', $tokens, '/v0/transactions');
  }

  public function deleteTransaction(Tokens $tokens): void {
    $this->tokenAuthRequest('DELETE', $tokens, '/v0/transactions');
  }

  public function updateTransaction(Tokens $tokens, array $params): void {
    $payload = array();
    if (array_key_exists('action', $params)) {
      $payload['action'] = $params['action'];
    }
    if (array_key_exists('note', $params)) {
      $payload['note'] = $params['note'];
    }
    $this->tokenAuthRequest('PATCH', $tokens, '/v0/transactions', $payload);
  }

  public function overrideTransaction(Tokens $tokens, array $params): void {
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

  public function validateSignature(string $secret, string $body, string $header) {
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
}
