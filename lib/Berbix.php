<?php

namespace Berbix;


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
  public $clientId;
  public $clientSecret;
  public $apiHost;

  public function __construct($cId=null, $cSecret=null, $host="https://api.berbix.com") {
    $this->clientId = $cId;
    $this->clientSecret = $cSecret;
    $this->apiHost = $host;
  }

  private function fetchTokens($path, $payload) {
    $url = $this->apiHost . $path;
    $content = json_encode($payload);

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    curl_setopt($curl, CURLOPT_USERPWD, $this->clientId . ":" . $this->clientSecret);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $content);

    $json_response = curl_exec($curl);

    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($status < 200 || $status >= 300) {
      throw new \Exception("unexpected status code returned $status, response was $json_response");
    }

    curl_close($curl);

    $result = json_decode($json_response, true);

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

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Authorization: Token ' . $userTokens->accessToken)
    );

    if ($method == 'POST') {
      curl_setopt($curl, CURLOPT_POST, true);
    }

    $json_response = curl_exec($curl);

    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($status < 200 || $status >= 300) {
      throw new \Exception("unexpected status code returned $status, response was $json_response");
    }

    curl_close($curl);

    return json_decode($json_response, true);
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