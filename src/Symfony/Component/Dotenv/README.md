Dotenv Component
================

Symfony Dotenv parses `.env` files to make environment variables stored in them
accessible via `$_SERVER` or `$_ENV`.

Getting Started
---------------

```bash
composer require symfony/dotenv
```

Usage
-----

> For an .env file with this format:

```env
YOUR_VARIABLE_NAME=my-string
```

```php
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

// you can also load several files
$dotenv->load(__DIR__.'/.env', __DIR__.'/.env.dev');

// overwrites existing env variables
$dotenv->overload(__DIR__.'/.env');

// loads .env, .env.local, and .env.$APP_ENV.local or .env.$APP_ENV
$dotenv->loadEnv(__DIR__.'/.env');

// Usage with $_ENV
$envVariable = $_ENV['YOUR_VARIABLE_NAME'];

// Usage with $_SERVER
$envVariable = $_SERVER['YOUR_VARIABLE_NAME'];
```

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
