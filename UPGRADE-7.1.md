UPGRADE FROM 7.0 to 7.1
=======================

Symfony 7.1 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/7.1/setup/upgrade_minor.html).

If you're upgrading from a version below 7.0, follow the [7.0 upgrade guide](UPGRADE-7.0.md) first.

Table of Contents
-----------------

Bundles

 * [FrameworkBundle](#FrameworkBundle)
 * [SecurityBundle](#SecurityBundle)
 * [TwigBundle](#TwigBundle)

Bridges

 * [DoctrineBridge](#DoctrineBridge)

Components

 * [AssetMapper](#AssetMapper)
 * [Cache](#Cache)
 * [DependencyInjection](#DependencyInjection)
 * [ExpressionLanguage](#ExpressionLanguage)
 * [Form](#Form)
 * [Intl](#Intl)
 * [HttpClient](#HttpClient)
 * [HttpKernel](#HttpKernel)
 * [Security](#Security)
 * [Serializer](#Serializer)
 * [Translation](#Translation)
 * [Workflow](#Workflow)

AssetMapper
-----------

 * Deprecate `ImportMapConfigReader::splitPackageNameAndFilePath()`, use `ImportMapEntry::splitPackageNameAndFilePath()` instead

Cache
-----

 * Deprecate `CouchbaseBucketAdapter`, use `CouchbaseCollectionAdapter` with Couchbase 3 instead
 * The algorithm for the default cache namespace changed from SHA256 to XXH128

DependencyInjection
-------------------

 * [BC BREAK] When used in the `prependExtension()` method, the `ContainerConfigurator::import()` method now prepends the configuration instead of appending it
 * Deprecate `#[TaggedIterator]` and `#[TaggedLocator]` attributes, use `#[AutowireIterator]` and `#[AutowireLocator]` instead

   *Before*
   ```php
   use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
   use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;

   class HandlerCollection
   {
       public function __construct(
           #[TaggedIterator('app.handler', indexAttribute: 'key')]
           iterable $handlers,

           #[TaggedLocator('app.handler')]
           private ContainerInterface $locator,
       ) {
       }
   }
   ```

   *After*
   ```php
   use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
   use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

   class HandlerCollection
   {
       public function __construct(
           #[AutowireIterator('app.handler', indexAttribute: 'key')]
           iterable $handlers,

           #[AutowireLocator('app.handler')]
           private ContainerInterface $locator,
       ) {
       }
   }
   ```

DoctrineBridge
--------------

 * Mark class `ProxyCacheWarmer` as `final`

ExpressionLanguage
------------------

 * Deprecate passing `null` as the allowed variable names to `ExpressionLanguage::lint()` and `Parser::lint()`,
   pass the `IGNORE_UNKNOWN_VARIABLES` flag instead to ignore unknown variables during linting

   *Before*
   ```php
   $expressionLanguage->lint('a + 1', null);
   ```

   *After*
   ```php
   use Symfony\Component\ExpressionLanguage\Parser;

   $expressionLanguage->lint('a + 1', [], Parser::IGNORE_UNKNOWN_VARIABLES);
   ```

Form
----

 * Deprecate not configuring the `default_protocol` option of the `UrlType`, it will default to `null` in 8.0 (the current default is `'http'`)

FrameworkBundle
---------------

 * [BC BREAK] Enabling `framework.rate_limiter` requires `symfony/rate-limiter` 7.1 or higher
 * Mark classes `ConfigBuilderCacheWarmer`, `Router`, `SerializerCacheWarmer`, `TranslationsCacheWarmer`, `Translator` and `ValidatorCacheWarmer` as `final`
 * Deprecate the `router.cache_dir` config option, the Router will always use the `kernel.build_dir` parameter
 * Reset env vars when resetting the container

HttpClient
----------

 * Deprecate the `setLogger()` methods of the `NoPrivateNetworkHttpClient`, `TraceableHttpClient` and `ScopingHttpClient` classes, configure the logger of the wrapped clients directly instead

   *Before*
   ```php
   // ...
   use Symfony\Component\HttpClient\HttpClient;
   use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;

   $publicClient = new NoPrivateNetworkHttpClient(HttpClient::create());
   $publicClient->setLogger(new Logger());
   ```

   *After*
   ```php
   // ...
   use Symfony\Component\HttpClient\HttpClient;
   use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;

   $client = HttpClient::create();
   $client->setLogger(new Logger());

   $publicClient = new NoPrivateNetworkHttpClient($client);
   ```

HttpKernel
----------

 * The `Extension` class is marked as internal, extend the `Extension` class from the DependencyInjection component instead
 * Deprecate `Extension::addAnnotatedClassesToCompile()`
 * Deprecate `AddAnnotatedClassesToCachePass`
 * Deprecate the `setAnnotatedClassCache()` and `getAnnotatedClassesToCompile()` methods of the `Kernel` class
 * Deprecate the `addAnnotatedClassesToCompile()` and `getAnnotatedClassesToCompile()` methods of the `Extension` class

Intl
----

 * [BC BREAK] Extracted `EmojiTransliterator` to a separate `symfony/emoji` component, the new FQCN is `Symfony\Component\Emoji\EmojiTransliterator`.
   You must install the `symfony/emoji` component if you're using the old `EmojiTransliterator` class in the Intl component.

Mailer
------

 * Postmark's "406 - Inactive recipient" API error code now results in a `PostmarkDeliveryEvent` instead of throwing a `HttpTransportException`

Security
--------

 * Change the first and second argument of `OidcTokenHandler` to `Jose\Component\Core\AlgorithmManager` and `Jose\Component\Core\JWKSet` respectively

SecurityBundle
--------------

 * Mark class `ExpressionCacheWarmer` as `final`
 * Deprecate options `algorithm` and `key` of `oidc` token handler, use
   `algorithms` and `keyset` instead

   *Before*
   ```yaml
   security:
       firewalls:
           main:
               access_token:
                   token_handler:
                       oidc:
                           algorithm: 'ES256'
                           key: '{"kty":"...","k":"..."}'
                           # ...
   ```

   *After*
   ```yaml
   security:
       firewalls:
           main:
               access_token:
                   token_handler:
                       oidc:
                           algorithms: ['ES256']
                           keyset: '{"keys":[{"kty":"...","k":"..."}]}'
                           # ...
   ```
 * Deprecate the `security.access_token_handler.oidc.jwk` service, use `security.access_token_handler.oidc.jwkset` instead

Serializer
----------

 * Deprecate the `withDefaultContructorArguments()` method of `AbstractNormalizerContextBuilder`, use `withDefaultConstructorArguments()` instead (note the typo in the old method name)

Translation
-----------

 * Mark class `DataCollectorTranslator` as `final`

TwigBundle
----------

 * Mark class `TemplateCacheWarmer` as `final`
 * Deprecate the `base_template_class` config option, this option is no-op when using Twig 3+

Validator
---------

 * Deprecate not passing a value for the `requireTld` option to the `Url` constraint (the default value will become `true` in 8.0)
 * Deprecate `Bic::INVALID_BANK_CODE_ERROR`, as ISO 9362 defines no restrictions on BIC bank code characters

Workflow
--------

 * Add method `getEnabledTransition()` to `WorkflowInterface`
 * Add `$nbToken` argument to `Marking::mark()` and `Marking::unmark()`
