Sendinblue Bridge
=================

Provides Sendinblue integration for Symfony Mailer.

Configuration example:

```env
# SMTP
MAILER_DSN=sendinblue+smtp://USERNAME:PASSWORD@default

# API
MAILER_DSN=sendinblue+api://KEY@default
```

where:
 - `KEY` is your Sendinblue API Key

With API, you can use custom headers.

```php
$params = ['param1' => 'foo', 'param2' => 'bar'];
$json = json_encode(['"custom_header_1' => 'custom_value_1']);

$email = new Email();
$email
    ->getHeaders()
    ->add(new MetadataHeader('custom', $json))
    ->add(new TagHeader('TagInHeaders1'))
    ->add(new TagHeader('TagInHeaders2'))
    ->addTextHeader('sender.ip', '1.2.3.4')
    ->addTextHeader('templateId', 1)
    ->addParameterizedHeader('params', 'params', $params)
    ->addTextHeader('foo', 'bar')
;
```

This example allow you to set :

 * templateId
 * params
 * tags
 * headers
    * sender.ip
    * X-Mailin-Custom

For more informations, you can refer to [Sendinblue API documentation](https://developers.sendinblue.com/reference#sendtransacemail).

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
