<?php

namespace Berbix;

class Tokens {
  public $refreshToken;
  public $accessToken;
  public $clientToken;
  public $expiry;
  public $transactionId;
  public $response; 

  public function __construct($refreshToken, $accessToken=null, $clientToken=null, $expiry=null, $transactionId=null, $response=null) {
    $this->refreshToken = $refreshToken;
    $this->accessToken = $accessToken;
    $this->clientToken = $clientToken;
    $this->expiry = $expiry;
    $this->transactionId = $transactionId;
    $this->response = $response;
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
