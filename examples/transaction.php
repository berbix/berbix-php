<?php

require __DIR__ . '../vendor/autoload.php';

$client = new \Berbix\Client(
  getenv("BERBIX_DEMO_CLIENT_SECRET"),
  array('apiHost' => getenv("BERBIX_DEMO_API_HOST")));

$client = new \Berbix\Client(
  getenv("BERBIX_DEMO_CLIENT_ID"),
  getenv("BERBIX_DEMO_CLIENT_SECRET"),
  array('apiHost' => getenv("BERBIX_DEMO_API_HOST")));

$manTokens = $client->createTransaction(array(
  'customerUid' => 9876,
));

var_dump($manTokens);

$continuation = $client->createContinuation($manTokens);

var_dump($continuation);

$tokens = $client->exchangeCode(getenv("BERBIX_DEMO_CODE"));

var_dump($tokens);

$user = $client->fetchTransaction($tokens);

var_dump($user);

$refreshOnly = \Berbix\Tokens::fromRefresh($tokens->refreshToken);

$transaction = $client->fetchTransaction($refreshOnly);

var_dump($refreshOnly);
var_dump($refreshed);

$client->deleteTransaction($manTokens);

?>
