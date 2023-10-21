Brevo Bridge
============

Provides Brevo integration for Symfony Mailer.
This was added upon Sendinblue's rebranding to Brevo.

Configuration example:

```env
# SMTP
MAILER_DSN=brevo+smtp://USERNAME:PASSWORD@default

# API
MAILER_DSN=brevo+api://KEY@default
```

where:
 - `KEY` is your Brevo API Key

With API, you can use custom headers.

```php
$params = ['param1' => 'foo', 'param2' => 'bar'];
$json = json_encode(['custom_header_1' => 'custom_value_1']);

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

This example allow you to set:

 * templateId
 * params
 * tags
 * headers
     * sender.ip
     * X-Mailin-Custom

For more information, you can refer to [Brevo API documentation](https://developers.brevo.com/reference/sendtransacemail).

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
