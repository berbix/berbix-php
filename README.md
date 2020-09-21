# Berbix PHP SDK

This Berbix PHP library provides simple interfaces to interact with the Berbix API.

## Installation

You can install the library through Composer.

```sh
composer require berbix/berbix-php
```

## Usage

### Constructing a client

```php
$client = new \Berbix\Client("your_api_secret_here");
```

### Create a transaction

```php
$transactionTokens = $client->createTransaction(array(
  'customerUid' => "internal_customer_uid", // ID for the user in internal database
  'templateKey' => "your_template_key", // Template key for this transaction
));
```

### Create tokens from refresh token

```php
$refreshToken = ''; // fetched from database
$transactionTokens = \Berbix\Tokens::fromRefresh($refreshToken);
```

### Fetch transaction data

```php
$transactionData = $client->fetchTransaction($transactionTokens);
```

## Reference

### `Client`

#### Methods

##### `constructor($clientId, $clientSecret, opts)`

Supported options:

- `environment` - Which environment the client uses, defaults to production.
- `httpClient` - An optional override for the default PHP HTTP client.

##### `createTransaction(options): Tokens`

Creates a transaction within Berbix to initialize the client SDK. Typically after creating
a transaction, you will want to store the refresh token in your database associated with the
currently active user session.

Supported options:

- `email` - Previously verified email address for a user.
- `phone` - Previously verified phone number for a user.
- `customerUid` - An ID or identifier for the user in your system.
- `templateKey` - The template key for this transaction.

##### `fetchTransaction(tokens: Tokens): object`

Fetches all of the information associated with the transaction. If the user has already completed the steps of the transaction, then this will include all of the elements of the transaction payload as described on the [Berbix developer docs](https://developers.berbix.com).

##### `refreshTokens(tokens: Tokens): void`

This is typically not needed to be called explicitly as it will be called by the higher-level
SDK methods, but can be used to get fresh client or access tokens.

##### `validateSignature(secret: string, body: string, header: string): boolean`

This method validates that the content of the webhook has not been forged. This should be called for every endpoint that is configured to receive a webhook from Berbix.

Parameters:

- `secret` - This is the secret associated with that webhook. NOTE: This is distinct from the API secret and can be found on the webhook configuration page of the dashboard.
- `body` - The full request body from the webhook. This should take the raw request body prior to parsing.
- `header` - The value in the 'X-Berbix-Signature' header.

##### `deleteTransaction(tokens: Tokens): void`

Permanently deletes all submitted data associated with the transaction corresponding to the tokens provided.

##### `updateTransaction(tokens: Tokens, parameters: object): object`

Changes a transaction's "action", for example upon review in your systems. Returns the updated transaction upon success.

Parameters:

- `action: string` - A string describing the action taken on the transaction. Typically this will either be "accept" or "reject".
- `note: string` - A string containing an optional note explaining the action taken.

##### `overrideTransaction(tokens: Tokens, parameters: object): void`

Completes a previously created transaction, and overrides its return payload and flags to match the provided parameters.

Parameters:

- `responsePayload: string` - A string describing the payload type to return when fetching transaction metadata, e.g. "us-dl". See [our testing guide](https://docs.berbix.com/docs/testing) for possible options.
- `flags: string[]` - An optional list of flags to associate with the transaction (independent of the payload's contents), e.g. ["id_under_18", "id_under_21"]. See [our flags documentation](https://docs.berbix.com/docs/id-flags) for a list of flags.
- `overrideFields: array(string => string)` - An optional mapping from a [transaction field](https://docs.berbix.com/reference#gettransactionmetadata) to the desired override value, e.g. `'overrideFields' => array('date_of_birth' => '2000-12-09')`

### `Tokens`

#### Properties

##### `accessToken: string`

This is the short-lived bearer token that the backend SDK uses to identify requests associated with a given transaction. This is not typically needed when using the higher-level SDK methods.

##### `clientToken: string`

This is the short-lived token that the frontend SDK uses to identify requests associated with a given transaction. After transaction creation, this will typically be sent to a frontend SDK.

##### `refreshToken: string`

This is the long-lived token that allows you to create new tokens after the short-lived tokens have expired. This is typically stored in the database associated with the given user session.

##### `transactionId: number`

The internal Berbix ID number associated with the transaction.

##### `expiry: Date`

The time at which the access and client tokens will expire.

#### Static methods

##### `fromRefresh(refreshToken: string): Tokens`

Creates a tokens object from a refresh token, which can be passed to higher-level SDK methods. The SDK will handle refreshing the tokens for accessing relevant data.
