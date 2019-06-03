OAuth Component
===============

A set of class which allows to use OAuth thanks to `HttpClient` and `OptionsResolver`.

**_Note: For now, this component does not support the server creation_**

## Usage

Using `symfony/oauth` outside of the framework is pretty straight forward.

Let's assume that we need to use the Github OAuth API.

First, let's instantiate the provider:

```php
<?php

require __DIR__ . 'vendor/autoload.php';

use Symfony\Components\HttpClient\HttpClient;
use Symfony\Components\OAuth\Provider\AuthorizationCodeProvider;

$client = HttpClient::create();

// The provider requires the main configuration keys
$provider = new AuthorizationCodeProvider($client, [
    'client_id' => 'foo',
    'client_secret' => 'bar',
    'redirect_uri' => 'http://foo.com/',
    'authorization_url' => 'https://github.com/login/oauth/authorize',
    'accessToken_url' => 'https://github.com/login/oauth/access_token',
    'userDetails_url' => 'https://api.github.com/user',
]);

// In the case that a provider cannot fetch an authorization code, a \RuntimeException is thrown
$authenticationCode = $provider->fetchAuthorizationCode([
    'scope' => 'public', 
    'state' => 'randomstring',
]);

// if you need it, you can use the `AuthorizationCodeResponse::get*` methods to access both code and state
// Once the authentication code is fetched, time to receive the access_token

$accessToken = $provider->fetchAccessToken(['code' => $authenticationCode->getCode()]); 

// Now, we can use use the received `AuthorizationCodeGrantAccessToken` object value to fetch the user details
// The $clientProfileLoader is prepared by the GenericProvider
$clientProfileLoader = $provider->prepareClientProfileLoader();

// The $userDate is a `ClientProfile` object
$userData = $clientProfileLoader->fetchClientProfile($accessToken->getTokenValue('access_token'));

echo $userData->get('login'); // Display Bob Foo
```

## Informations

- The constructor arguments order of `GenericProvider` can probably be improved.
- The `AuthorizationCodeGrantAccessToken::getOptionValue()` is similar to `ParameterBag::get()`.
- The `AuthorizationCodeResponse` isn't critical as it's a simple "DTO", we can
  easily return an array.
- For now, the `Symfony\Components\OAuth\Provider\GenericProvider` doesn't allow
  to fetch the user details directly (as it's not part of the RFC), 
  the final request is up to the user.
- If an error occurs, no message are returned for now, it could be a good idea
  to return the `error` key returned by the API.
- No implementation is available on the full-stack framework for now.
- The `Symfony\Component\OAuth\Provider\ProviderInterface` can benefit of 
  having the `fetchAuthorizationCode` and `fetchAccessToken` methods if a 
  contract is created.
- The `\RuntimeException` which is thrown during the `$provider->fetchAuthenticationCode` call (if the provider can't use it)
  can be moved to a `trigger_error`. 

## Supported providers

For now, the component deliver the main providers described in the RFC: https://tools.ietf.org/html/rfc6749

## Tests

The component isn't fully tested as it's considered as a POC, 
the main providers are tested and validated.
The tokens should be fully tested. 

## Framework integration (WIP)

**This part isn't ready to use, it's just an idea**

As an application can use multiples providers, it could be a good idea to 
allows to use a factory (like `HttpClient`?):

```yaml
framework:
    oauth:
        redirect_uri: '%env(REDIRECT_URI)%' # Can be useful in order to share the configuration?
        providers:
            github:
                type: 'authorization_code'
                    client_id: '%env(CLIENT_ID)%'
                    client_secret: '%env(CLIENT_SECRET)%'
                    authorization_url: '%env(API_AUTHORIZATION_URL)%'
                    access_token_url: '%env(API_ACCESS_TOKEN_URL)%'
            google: 
                type: 'authorization_code' # Can be authorization_code, implicit, client_credentials or resource_owner
```

This way, a newly created service can be accessed via `@oauth.github`, 
time to configure the `security.yaml`:

```yaml
security:
    providers:
        github_oauth:
            oauth:
                provider: '@oauth.github' # Or maybe '@oauth.google'? 
    
    firewalls:
        main:
            provider: github_oauth
            
```

Let's imagine that we need to inject the provider in a service:

```php
<?php

namespace App\Services;

use Symfony\Component\OAuth\Provider\ProviderInterface; // Maybe a contract?

class Foo 
{
    private $oauthGithub;
    
    public function __construct(ProviderInterface $oauthGithub) 
    {
        $this->oauthGithub = $oauthGithub;
    }
    
    // ...
}
```

### Creating a custom provider

As the component allows to use a `ProviderInterface`, creating a custom provider
is as easy as it sounds: 

```php
<?php

use Symfony\Component\OAuth\Provider\ProviderInterface;

class FooProvider implements ProviderInterface 
{
    public function fetchAccessToken(array $options,array $headers = [],string $method = 'GET')
    {
        // ...
    }
    
    // ...
}
```

Or if needed, we can directly extends the `GenericProvider` class. 
