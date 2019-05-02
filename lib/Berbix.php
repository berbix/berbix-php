<?php

namespace Berbix;


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


class UserTokens {
  public $refreshToken;
  public $accessToken;
  public $expiry;

  public function __construct($refresh, $access=null, $exp=null) {
    $this->refreshToken = $refresh;
    $this->accessToken = $access;
    $this->expiry = $exp;
  }

  public function refresh($access, $exp) {
    $this->accessToken = $access;
    $this->expiry = $exp;
  }

  public function needsRefresh() {
    return $this->accessToken == null || $this->expiry == null || $this->expiry < time();
  }
}


class Client {
  private $clientId;
  private $clientSecret;
  private $apiHost;
  private $httpClient;

  public function __construct($cId=null, $cSecret=null, $host="https://api.berbix.com", $http=null) {
    $this->clientId = $cId;
    $this->clientSecret = $cSecret;
    $this->apiHost = $host;
    if ($http == null) {
      $this->httpClient = new HTTPClient();
    } else {
      $this->httpClient = $http;
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
    );

    $result = $this->httpClient->makeRequest("POST", $url, $headers, $payload);

    return new UserTokens($result['refresh_token'], $result['access_token'], $result['expires_in'] + time());
  }

  public function createUser($opts) {
    $payload = array();
    if (array_key_exists('email', $opts)) {
      $payload['email'] = $opts['email'];
    }
    if (array_key_exists('phone', $opts)) {
      $payload['phone'] = $opts['phone'];
    }
    if (array_key_exists('customerUid', $opts)) {
      $payload['customer_uid'] = $opts['customerUid'];
    }
    return $this->fetchTokens("/v0/users", $payload);
  }

  public function refreshTokens($userTokens) {
    return $this->fetchTokens("/v0/tokens", array(
      'refresh_token' => $userTokens->refreshToken,
      'grant_type' => 'refresh_token',
    ));
  }

  public function exchangeCode($code) {
    return $this->fetchTokens("/v0/tokens", array(
      'code' => $code,
      'grant_type' => 'authorization_code',
    ));
  }

  private function refreshIfNecessary($userTokens) {
    if ($userTokens->needsRefresh()) {
      $refreshed = $this->refreshTokens($userTokens);
      $userTokens->refresh($refreshed->accessToken, $refreshed->expiry);
    }
  }

  private function tokenAuthRequest($method, $userTokens, $path) {
    $this->refreshIfNecessary($userTokens);

    $url = $this->apiHost . $path;

    $headers = array(
      'Content-Type: application/json',
      'Authorization: Token ' . $userTokens->accessToken,
    );

    return $this->httpClient->makeRequest($method, $url, $headers);
  }

  public function fetchUser($userTokens) {
    return $this->tokenAuthRequest('GET', $userTokens, '/v0/users');
  }

  public function createContinuation($userTokens) {
    $result = $this->tokenAuthRequest('POST', $userTokens, '/v0/continuations');
    return $result['value'];
  }
}

?>