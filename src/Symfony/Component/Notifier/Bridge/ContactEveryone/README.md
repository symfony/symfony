Contact Everyone Notifier
=========================

Provides [Contact everyone](https://www.orange-business.com/fr/produits/contact-everyone) integration for Symfony Notifier.

DSN example
-----------

```
CONTACT_EVERYONE_DSN=contact-everyone://TOKEN@default?&diffusionname=DIFFUSION_NAME&category=CATEGORY
```

where:
 - `TOKEN` is your Contact Everyone API token
 - `DIFFUSION_NAME` (optional) allows you to define the label of the diffusion that will be displayed in the event logs.
 - `CATEGORY` (optional) allows you to define the label of the category that will be displayed in the event logs.

This bridge uses the light version of Contact Everyone API.

See your account info at https://contact-everyone.orange-business.com/#/login

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
