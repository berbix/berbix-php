<?php

require __DIR__ . '/../vendor/autoload.php';

$client = new \Berbix\Client(
  getenv("BERBIX_DEMO_API_SECRET"),
  array('apiHost' => getenv("BERBIX_DEMO_API_HOST")));

$hosted = $client->createHostedTransaction(array(
  'customerUid' => 9876,
  'templateKey' => getenv("BERBIX_DEMO_TEMPLATE_KEY"),
));

if ($hosted['hostedUrl'] == "") {
  print("expected a hosted URL, got none");
  exit(1);
}

if ($hosted['tokens']->refreshToken == "") {
  print("expected a refresh token, got none");
  exit(1);
}

var_dump($hosted);

$tokens = $client->createTransaction(array(
  'customerUid' => 9876,
  'templateKey' => getenv("BERBIX_DEMO_TEMPLATE_KEY"),
));

if ($tokens->refreshToken == "") {
  print("expected a refresh token, got none");
  exit(1);
}

var_dump($tokens);

$transaction = $client->fetchTransaction($tokens);

var_dump($transaction);

if ($transaction["entity"] != "transaction_metadata") {
  print("expected a transaction metadata entity");
  exit(1);
}

$refreshOnly = \Berbix\Tokens::fromRefresh($tokens->refreshToken);

$transaction2 = $client->fetchTransaction($refreshOnly);

var_dump($refreshOnly);
var_dump($refreshed);

if ($transaction2["entity"] != "transaction_metadata") {
  print("expected a transaction metadata entity");
  exit(1);
}

$transaction = $client->overrideTransaction($tokens, array(
  'responsePayload' => 'us-dl',
  'flags' => array('id_under_18', 'id_under_21'),
  'overrideFields' => array('date_of_birth' => '2000-12-09'),
));

var_dump($transaction);

$transaction = $client->updateTransaction($tokens, array(
  'action' => 'reject',
  'note' => 'we can not accept this person',
));

var_dump($transaction);

$client->deleteTransaction($tokens);
$client->deleteTransaction($hosted['tokens']);
