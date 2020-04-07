<?php

namespace Berbix;

use Exception;

class HTTPClient {
  public function makeRequest($method, $url, $headers, $payload=null) {
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    if ($method == 'POST') {
      curl_setopt($curl, CURLOPT_POST, true);
    }

    if ($method == 'DELETE') {
      curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    if ($payload != null) {
      $content = json_encode($payload);
      array_push($headers, "Content-Length: " . strlen($content));
      curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
    }

    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

    $json_response = curl_exec($curl);

    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($status < 200 || $status >= 300) {
      throw new Exception("unexpected status code returned $status, response was $json_response");
    }

    curl_close($curl);

    return json_decode($json_response, true);
  }
}
