<?php

require __DIR__ . '../vendor/autoload.php';

$client = new \Berbix\Client(
  getenv("BERBIX_DEMO_API_SECRET"),
  array('apiHost' => getenv("BERBIX_DEMO_API_HOST")));

$tokens = $client->createTransaction(array(
  'customerUid' => 9876,
  'templateKey' => getenv("BERBIX_DEMO_TEMPLATE_KEY"),
));

var_dump($tokens);

$transaction = $client->fetchTransaction($tokens);

var_dump($transaction);

$refreshOnly = \Berbix\Tokens::fromRefresh($tokens->refreshToken);

$transaction = $client->fetchTransaction($refreshOnly);

var_dump($refreshOnly);
var_dump($refreshed);

$transaction = $client->overrideTransaction($tokens, array(
  'responsePayload' => 'us-dl',
  'flags' => array('id_under_18', 'id_under_21'),
));

var_dump($transaction);

$transaction = $client->updateTransaction($tokens, array(
  'action' => 'reject',
  'note' => 'we can not accept this person',
));

var_dump($transaction);

// Uncomment the following line to delete the newly-created transaction
// $client->deleteTransaction($tokens);
