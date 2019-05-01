# Berbix PHP SDK

This Berbix PHP library provides simple interfaces to interact with the Berbix API.

## Usage

### Constructing the client

    require_once('/path/to/berbix-php/init.php');

    $client = new \Berbix\Client(
      "your_client_id_here",
      "your_client_secret_here");

### Fetching user tokens

    $userTokens = $client->exchangeCode(code)

### Fetching user data

    $user = $client->fetchUser($userTokens);

### User tokens from storage

    $refreshToken = ''; // fetched from database
    $userTokens = new \Berbix\UserTokens($refreshToken);
