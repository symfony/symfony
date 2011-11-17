How to update your project?
===========================

This document explains how to upgrade from one Symfony2 version to the next
one. It only discusses changes that need to be done when using the "public"
API of the framework. If you "hack" the core, you should probably follow the
timeline closely anyway.

RC4 to RC5
----------

* The `MapFileClassLoader` has been removed in favor of a new
  `MapClassLoader`.

* The `exception_controller` setting has been moved from the `framework`
  section to the `twig` one.

* The custom error pages must now reference `TwigBundle` instead of
  `FrameworkBundle` (see
  http://symfony.com/doc/2.0/cookbook/controller/error_pages.html)

* `EntityUserProvider` class has been moved and FQCN changed from
  `Symfony\Component\Security\Core\User\EntityUserProvider` to
  `Symfony\Bridge\Doctrine\Security\User\EntityUserProvider`.

* Cookies access from `HeaderBag` has been removed. Accessing Request cookies
  must be done via `Request::$cookies`.

* `ResponseHeaderBag::getCookie()` and `ResponseHeaderBag::hasCookie()`
  methods were removed.

* The method `ResponseHeaderBag::getCookies()` now supports an argument for the
  returned format (possible values are `ResponseHeaderBag::COOKIES_FLAT`
  (default value) or `ResponseHeaderBag::COOKIES_ARRAY`).

    * `ResponseHeaderBag::COOKIES_FLAT` returns a simple array (the array keys
      are not cookie names anymore):

        * array(0 => `a Cookie instance`, 1 => `another Cookie instance`)

    * `ResponseHeaderBag::COOKIES_ARRAY` returns a multi-dimensional array:

        * array(`the domain` => array(`the path` => array(`the cookie name` => `a Cookie instance`)))

* Removed the guesser for the Choice constraint as the constraint only knows
  about the valid keys, and not their values.

* The configuration of MonologBundle has been refactored.

    * Only services are supported for the processors. They are now registered
      using the `monolog.processor` tag which accept three optionnal attributes:

        * `handler`: the name of an handler to register it only for a specific handler
        * `channel`: to register it only for one logging channel (exclusive with `handler`)
        * `method`: The method used to process the record (`__invoke` is used if not set)

    * The email_prototype for the `SwiftMailerHandler` only accept a service id now.

        * Before:

            email_prototype: @acme_demo.monolog.email_prototype

        * After:

            email_prototype: acme_demo.monolog.email_prototype

          or if you want to use a factory for the prototype:

            email_prototype:
                id:     acme_demo.monolog.email_prototype
                method: getPrototype

* To avoid security issues, HTTP headers coming from proxies are not trusted
  anymore by default (like `HTTP_X_FORWARDED_FOR`, `X_FORWARDED_PROTO`, and
  `X_FORWARDED_HOST`). If your application is behind a reverse proxy, add the
  following configuration:

        framework:
            trust_proxy_headers: true

* To avoid hidden naming collisions, the AbstractType does not try to define
  the name of form types magically. You now need to implement the `getName()`
  method explicitly when creating a custom type.

* Renamed some methods to follow the naming conventions:

        Session::getAttributes() -> Session::all()
        Session::setAttributes() -> Session::replace()

* {_locale} is not supported in paths in the access_control section anymore. You can
  rewrite the paths using a regular expression such as "(?:[a-z]{2})".

RC3 to RC4
----------

* Annotation classes must be annotated with @Annotation
  (see the validator constraints for examples)

* Annotations are not using the PHP autoloading but their own mechanism. This
  allows much more control about possible failure states. To make your code
  work, add the following lines at the end of your `autoload.php` file:

        use Doctrine\Common\Annotations\AnnotationRegistry;

        AnnotationRegistry::registerLoader(function($class) use ($loader) {
            $loader->loadClass($class);
            return class_exists($class, false);
        });

        AnnotationRegistry::registerFile(
            __DIR__.'/../vendor/doctrine/lib/Doctrine/ORM/Mapping/Driver/DoctrineAnnotations.php'
        );

  The `$loader` variable is an instance of `UniversalClassLoader`.
  Additionally you might have to adjust the ORM path to the
  `DoctrineAnnotations.php`. If you are not using the `UniversalClassLoader`
  see the [Doctrine Annotations
  documentation](http://www.doctrine-project.org/docs/common/2.1/en/reference/annotations.html)
  for more details on how to register annotations.

beta5 to RC1
------------

* Renamed `Symfony\Bundle\FrameworkBundle\Command\Command` to
  `Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand`

* Removed the routing `AnnotGlobLoader` class

* Some blocks in the Twig Form templates have been renamed to avoid
  collisions:

    * `container_attributes` to `widget_container_attributes`
    * `attributes` to `widget_attributes`
    * `options` to `widget_choice_options`

* Event changes:

    * All listeners must now be tagged with `kernel.event_listener` instead of
      `kernel.listener`.
    * Kernel events are now properly prefixed with `kernel` instead of `core`:

        * Before:

                <tag name="kernel.listener" event="core.request" method="onCoreRequest" />

        * After:

                <tag name="kernel.event_listener" event="kernel.request" method="onKernelRequest" />

        Note: the method can of course remain as `onCoreRequest`, but renaming it
        as well for consistency with future projects makes sense.

    * The `Symfony\Component\HttpKernel\CoreEvents` class has been renamed to
      `Symfony\Component\HttpKernel\KernelEvents`

* `TrueValidator` and `FalseValidator` constraints validators no longer accepts any value as valid data.

beta4 to beta5
--------------

* `UserProviderInterface::loadUser()` has been renamed to
  `UserProviderInterface::refreshUser()` to make the goal of the method
  clearer.

* The `$kernel` property on `WebTestCase` is now static. Change any instances
  of `$this->kernel` in your functional tests to `self::$kernel`.

* The AsseticBundle has been moved to its own repository (it still bundled
  with Symfony SE).

* Yaml Component:

    * Exception classes have been moved to their own namespace
    * `Yaml::load()` has been renamed to `Yaml::parse()`

* The File classes from `HttpFoundation` have been refactored:

    * `Symfony\Component\HttpFoundation\File\File` has a new API:

       * It now extends `\SplFileInfo`:

           * former `getName()` equivalent is `getBasename()`,
           * former `getDirectory()` equivalent is `getPath()`,
           * former `getPath()` equivalent is `getRealPath()`.

       * the `move()` method now creates the target directory when it does not exist,

       * `getExtension()` and `guessExtension()` do not return the extension
          with a leading `.` anymore

    * `Symfony\Component\HttpFoundation\File\UploadedFile` has a new API:

        * The constructor has a new Boolean parameter that must be set to true
          in test mode only in order to be able to move the file. This parameter
          is not intended to be set to true from outside of the core files.

        * `getMimeType()` now always returns the mime type of the underlying file.
           Use `getClientMimeType()` to get the mime type from the request.

        * `getSize()` now always returns the size of the underlying file.
           Use `getClientSize()` to get the file size from the request.

        * Use `getClientOriginalName()` to retrieve the original name from the
          request.

* The `extensions` setting for Twig has been removed. There is now only one
  way to register Twig extensions, via the `twig.extension` tag.

* The stack of Monolog handlers now bubbles the records by default. To stop
  the propagation you need to configure the bubbling explicitly.

* Expanded the `SerializerInterface`, while reducing the number of public
  methods in the Serializer class itself breaking BC and adding component
  specific Exception classes.

* The `FileType` Form class has been heavily changed:

    * The temporary storage has been removed.

    * The file type `type` option has also been removed (the new behavior is
      the same as when the `type` was set to `file` before).

    * The file input is now rendered as any other input field.

* The `em` option of the Doctrine `EntityType` class now takes the entity
  manager name instead of the EntityManager instance. If you don't pass this
  option, the default Entity Manager will be used as before.

* In the Console component: `Command::getFullname()` and
  `Command::getNamespace()` have been removed (`Command::getName()` behavior
  is now the same as the old `Command::getFullname()`).

* Default Twig form templates have been moved to the Twig bridge. Here is how
  you can reference them now from a template or in a configuration setting:

    Before:

        TwigBundle:Form:div_layout.html.twig

    After:

        form_div_layout.html.twig

* All settings regarding the cache warmers have been removed.

* `Response::isRedirected()` has been merged with `Response::isRedirect()`

beta3 to beta4
--------------

* `Client::getProfiler` has been removed in favor of `Client::getProfile`,
  which returns an instance of `Profile`.

* Some `UniversalClassLoader` methods have been renamed:

    * `registerPrefixFallback` to `registerPrefixFallbacks`
    * `registerNamespaceFallback` to `registerNamespaceFallbacks`

* The event system has been made more flexible. A listener can now be any
  valid PHP callable.

    * `EventDispatcher::addListener($eventName, $listener, $priority = 0)`:
        * `$eventName` is the event name (cannot be an array anymore),
        * `$listener` is a PHP callable.

    * The events classes and constants have been renamed:

        * Old class name `Symfony\Component\Form\Events` and constants:

                Events::preBind = 'preBind'
                Events::postBind = 'postBind'
                Events::preSetData = 'preSetData'
                Events::postSetData = 'postSetData'
                Events::onBindClientData = 'onBindClientData'
                Events::onBindNormData = 'onBindNormData'
                Events::onSetData = 'onSetData'

        * New class name `Symfony\Component\Form\FormEvents` and constants:

                FormEvents::PRE_BIND = 'form.pre_bind'
                FormEvents::POST_BIND = 'form.post_bind'
                FormEvents::PRE_SET_DATA = 'form.pre_set_data'
                FormEvents::POST_SET_DATA = 'form.post_set_data'
                FormEvents::BIND_CLIENT_DATA = 'form.bind_client_data'
                FormEvents::BIND_NORM_DATA = 'form.bind_norm_data'
                FormEvents::SET_DATA = 'form.set_data'

        * Old class name `Symfony\Component\HttpKernel\Events` and constants:

                Events::onCoreRequest = 'onCoreRequest'
                Events::onCoreException = 'onCoreException'
                Events::onCoreView = 'onCoreView'
                Events::onCoreController = 'onCoreController'
                Events::onCoreResponse = 'onCoreResponse'

        * New class name `Symfony\Component\HttpKernel\CoreEvents` and constants:

                CoreEvents::REQUEST = 'core.request'
                CoreEvents::EXCEPTION = 'core.exception'
                CoreEvents::VIEW = 'core.view'
                CoreEvents::CONTROLLER = 'core.controller'
                CoreEvents::RESPONSE = 'core.response'

        * Old class name `Symfony\Component\Security\Http\Events` and constants:

                Events::onSecurityInteractiveLogin = 'onSecurityInteractiveLogin'
                Events::onSecuritySwitchUser = 'onSecuritySwitchUser'

        * New class name `Symfony\Component\Security\Http\SecurityEvents` and constants:

                SecurityEvents::INTERACTIVE_LOGIN = 'security.interactive_login'
                SecurityEvents::SWITCH_USER = 'security.switch_user'

    * `addListenerService` now only takes a single event name as its first
      argument,

    * Tags in configuration must now set the method to call:

        * Before:

                <tag name="kernel.listener" event="onCoreRequest" />

        * After:

                <tag name="kernel.listener" event="core.request" method="onCoreRequest" />

    * Subscribers must now always return a hash:

        * Before:

                public static function getSubscribedEvents()
                {
                    return Events::onBindNormData;
                }

        * After:

                public static function getSubscribedEvents()
                {
                    return array(FormEvents::BIND_NORM_DATA => 'onBindNormData');
                }

* Form `DateType` parameter `single-text` changed to `single_text`
* Form field label helpers now accepts setting attributes, i.e.:

```html+jinja
{{ form_label(form.name, 'Custom label', { 'attr': {'class': 'name_field'} }) }}
```

* In order to use Swiftmailer, you should now register its "init.php" file via
  the autoloader ("app/autoloader.php") and remove the `Swift_` prefix from
  the autoloader. For an example on how this should be done, see the Standard
  Distribution
  [autoload.php](https://github.com/symfony/symfony-standard/blob/v2.0.0BETA4/app/autoload.php#L29).

beta2 to beta3
--------------

* The settings under `framework.annotations` have changed slightly:

    Before:

        framework:
            annotations:
                cache: file
                file_cache:
                    debug: true
                    dir: /foo

    After:

        framework:
            annotations:
                cache: file
                debug: true
                file_cache_dir: /foo

beta1 to beta2
--------------

* The annotation parsing process has been changed (it now uses Doctrine Common
  3.0). All annotations which are used in a class must now be imported (just
  like you import PHP namespaces with the "use" statement):

  Before:

``` php
<?php

/**
 * @orm:Entity
 */
class AcmeUser
{
    /**
     * @orm:Id
     * @orm:GeneratedValue(strategy = "AUTO")
     * @orm:Column(type="integer")
     * @var integer
     */
    private $id;

    /**
     * @orm:Column(type="string", nullable=false)
     * @assert:NotBlank
     * @var string
     */
    private $name;
}
```
  After:

``` php
<?php

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity
 */
class AcmeUser
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     *
     * @var integer
     */
    private $id;

    /**
     * @ORM\Column(type="string", nullable=false)
     * @Assert\NotBlank
     *
     * @var string
     */
    private $name;
}
```

* The `Set` constraint has been removed as it is not required anymore.

Before:

``` php
<?php

/**
 * @orm:Entity
 */
class AcmeEntity
{
    /**
     * @assert:Set({@assert:Callback(...), @assert:Callback(...)})
     */
    private $foo;
}
```
After:

``` php
<?php

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints\Callback;

/**
 * @ORM\Entity
 */
class AcmeEntity
{
    /**
     * @Callback(...)
     * @Callback(...)
     */
    private $foo;
}
```

* The config under `framework.validation.annotations` has been removed and was
  replaced with a boolean flag `framework.validation.enable_annotations` which
  defaults to false.

* Forms must now be explicitly enabled (automatically done in Symfony SE):

        framework:
            form: ~

    Which is equivalent to:

        framework:
            form:
                enabled: true

* The Routing Exceptions have been moved:

    Before:

        Symfony\Component\Routing\Matcher\Exception\Exception
        Symfony\Component\Routing\Matcher\Exception\NotFoundException
        Symfony\Component\Routing\Matcher\Exception\MethodNotAllowedException

    After:

        Symfony\Component\Routing\Exception\Exception
        Symfony\Component\Routing\Exception\NotFoundException
        Symfony\Component\Routing\Exception\MethodNotAllowedException

* The form component's `csrf_page_id` option has been renamed to
  `intention`.

* The `error_handler` setting has been removed. The `ErrorHandler` class
  is now managed directly by Symfony SE in `AppKernel`.

* The Doctrine metadata files has moved from
  `Resources/config/doctrine/metadata/orm/` to `Resources/config/doctrine`,
  the extension from `.dcm.yml` to `.orm.yml`, and the file name has been
  changed to the short class name.

    Before:

        Resources/config/doctrine/metadata/orm/Bundle.Entity.dcm.xml
        Resources/config/doctrine/metadata/orm/Bundle.Entity.dcm.yml

    After:

        Resources/config/doctrine/Entity.orm.xml
        Resources/config/doctrine/Entity.orm.yml

* With the introduction of a new Doctrine Registry class, the following
  parameters have been removed (replaced by methods on the `doctrine`
  service):

   * `doctrine.orm.entity_managers`
   * `doctrine.orm.default_entity_manager`
   * `doctrine.dbal.default_connection`

    Before:

        $container->getParameter('doctrine.orm.entity_managers')
        $container->getParameter('doctrine.orm.default_entity_manager')
        $container->getParameter('doctrine.orm.default_connection')

    After:

        $container->get('doctrine')->getEntityManagerNames()
        $container->get('doctrine')->getDefaultEntityManagerName()
        $container->get('doctrine')->getDefaultConnectionName()

    But you don't really need to use these methods anymore, as to get an entity
    manager, you can now use the registry directly:

    Before:

        $em = $this->get('doctrine.orm.entity_manager');
        $em = $this->get('doctrine.orm.foobar_entity_manager');

    After:

        $em = $this->get('doctrine')->getEntityManager();
        $em = $this->get('doctrine')->getEntityManager('foobar');

* The `doctrine:generate:entities` arguments and options changed. Run
  `./app/console doctrine:generate:entities --help` for more information about
  the new syntax.

* The `doctrine:generate:repositories` command has been removed. The
  functionality has been moved to the `doctrine:generate:entities`.

* Doctrine event subscribers now use a unique "doctrine.event_subscriber" tag.
  Doctrine event listeners also use a unique "doctrine.event_listener" tag. To
  specify a connection, use the optional "connection" attribute.

    Before:

        listener:
            class: MyEventListener
            tags:
                - { name: doctrine.common.event_listener, event: name }
                - { name: doctrine.dbal.default_event_listener, event: name }
        subscriber:
            class: MyEventSubscriber
            tags:
                - { name: doctrine.common.event_subscriber }
                - { name: doctrine.dbal.default_event_subscriber }

    After:

        listener:
            class: MyEventListener
            tags:
                - { name: doctrine.event_listener, event: name }                      # register for all connections
                - { name: doctrine.event_listener, event: name, connection: default } # only for the default connection
        subscriber:
            class: MyEventSubscriber
            tags:
                - { name: doctrine.event_subscriber }                      # register for all connections
                - { name: doctrine.event_subscriber, connection: default } # only for the default connection

* Application translations are now stored in the `Resources` directory:

    Before:

        app/translations/catalogue.fr.xml

    After:

        app/Resources/translations/catalogue.fr.xml

* The option `modifiable` of the `collection` form type was split into two
  options `allow_add` and `allow_delete`.

    Before:

        $builder->add('tags', 'collection', array(
            'type' => 'text',
            'modifiable' => true,
        ));

    After:

        $builder->add('tags', 'collection', array(
            'type' => 'text',
            'allow_add' => true,
            'allow_delete' => true,
        ));

* `Request::hasSession()` has been renamed to `Request::hasPreviousSession()`. The
  method `hasSession()` still exists, but only checks if the request contains a
  session object, not if the session was started in a previous request.

* Serializer: The NormalizerInterface's `supports()` method has been split in
  two methods: `supportsNormalization()` and `supportsDenormalization()`.

* `ParameterBag::getDeep()` has been removed, and is replaced with a boolean flag
  on the `ParameterBag::get()` method.

* Serializer: `AbstractEncoder` & `AbstractNormalizer` were renamed to
  `SerializerAwareEncoder` & `SerializerAwareNormalizer`.

* Serializer: The `$properties` argument has been dropped from all interfaces.

* Form: Renamed option value `text` of `widget` option of the `date` type was
  renamed to `single-text`. `text` indicates to use separate text boxes now
  (like for the `time` type).

* Form: Renamed view variable `name` to `full_name`. The variable `name` now
  contains the local, short name (equivalent to `$form->getName()`).

PR12 to beta1
-------------

* The CSRF secret configuration has been moved to a mandatory global `secret`
  setting (as the secret is now used for everything and not just CSRF):

    Before:

        framework:
            csrf_protection:
                secret: S3cr3t

    After:

        framework:
            secret: S3cr3t

* The `File::getWebPath()` and `File::rename()` methods have been removed, as
  well as the `framework.document_root` configuration setting.

* The `File::getDefaultExtension()` method has been renamed to `File::guessExtension()`.
  The renamed method now returns null if it cannot guess the extension.

* The `session` configuration has been refactored:

  * The `class` option has been removed (use the `session.class` parameter
    instead);

  * The PDO session storage configuration has been removed (a cookbook recipe
    is in the work);

  * The `storage_id` option now takes a service id instead of just part of it.

* The `DoctrineMigrationsBundle` and `DoctrineFixturesBundle` bundles have
  been moved to their own repositories.

* The form framework has been refactored extensively (more information in the
  documentation).

* The `trans` tag does not accept a message as an argument anymore:

        {% trans "foo" %}
        {% trans foo %}

    Use the long version the tags or the filter instead:

        {% trans %}foo{% endtrans %}
        {{ foo|trans }}

    This has been done to clarify the usage of the tag and filter and also to
    make it clearer when the automatic output escaping rules are applied (see
    the doc for more information).

* Some methods in the DependencyInjection component's `ContainerBuilder` and
  `Definition` classes have been renamed to be more specific and consistent:

    Before:

        $container->remove('my_definition');
        $definition->setArgument(0, 'foo');

    After:

        $container->removeDefinition('my_definition');
        $definition->replaceArgument(0, 'foo');

* In the rememberme configuration, the `token_provider key` now expects a real
  service id instead of only a suffix.

PR11 to PR12
------------

* `HttpFoundation\Cookie::getExpire()` was renamed to `getExpiresTime()`

* XML configurations have been normalized. All tags with only one attribute
  have been converted to tag content:

    Before:

        <bundle name="MyBundle" />
        <app:engine id="twig" />
        <twig:extension id="twig.extension.debug" />

    After:

        <bundle>MyBundle</bundle>
        <app:engine>twig</app:engine>
        <twig:extension>twig.extension.debug</twig:extension>

* Fixes a critical security issue which allowed all users to switch to
  arbitrary accounts when the SwitchUserListener was activated. Configurations
  which do not use the SwitchUserListener are not affected.

* The Dependency Injection Container now strongly validates the references of
  all your services at the end of its compilation process. If you have invalid
  references this will result in a compile-time exception instead of a run-time
  exception (the previous behavior).

PR10 to PR11
------------

* Extension configuration classes should now implement the
  `Symfony\Component\Config\Definition\ConfigurationInterface` interface. Note
  that the BC is kept but implementing this interface in your extensions will
  allow for further developments.

* The `fingerscrossed` Monolog option has been renamed to `fingers_crossed`.

PR9 to PR10
-----------

* Bundle logical names earned back their `Bundle` suffix:

    *Controllers*: `Blog:Post:show` -> `BlogBundle:Post:show`

    *Templates*:   `Blog:Post:show.html.twig` -> `BlogBundle:Post:show.html.twig`

    *Resources*:   `@Blog/Resources/config/blog.xml` -> `@BlogBundle/Resources/config/blog.xml`

    *Doctrine*:    `$em->find('Blog:Post', $id)` -> `$em->find('BlogBundle:Post', $id)`

* `ZendBundle` has been replaced by `MonologBundle`. Have a look at the
  changes made to Symfony SE to see how to upgrade your projects:
  https://github.com/symfony/symfony-standard/pull/30/files

* Almost all core bundles parameters have been removed. You should use the
  settings exposed by the bundle extension configuration instead.

* Some core bundles service names changed for better consistency.

* Namespace for validators has changed from `validation` to `assert` (it was
  announced for PR9 but it was not the case then):

    Before:

        @validation:NotNull

    After:

        @assert:NotNull

    Moreover, the `Assert` prefix used for some constraints has been removed
    (`AssertTrue` to `True`).

* `ApplicationTester::getDisplay()` and `CommandTester::getDisplay()` method
  now return the command exit code

PR8 to PR9
----------

* `Symfony\Bundle\FrameworkBundle\Util\Filesystem` has been moved to
  `Symfony\Component\HttpKernel\Util\Filesystem`

* The `Execute` constraint has been renamed to `Callback`

* The HTTP exceptions classes signatures have changed:

    Before:

        throw new NotFoundHttpException('Not Found', $message, 0, $e);

    After:

        throw new NotFoundHttpException($message, $e);

* The RequestMatcher class does not add `^` and `$` anymore to regexp.

    You need to update your security configuration accordingly for instance:

    Before:

        pattern:  /_profiler.*
        pattern:  /login

    After:

        pattern:  ^/_profiler
        pattern:  ^/login$

* Global templates under `app/` moved to a new location (old directory did not
  work anyway):

    Before:

        app/views/base.html.twig
        app/views/AcmeDemoBundle/base.html.twig

    After:

        app/Resources/views/base.html.twig
        app/Resources/AcmeDemo/views/base.html.twig

* Bundle logical names lose their `Bundle` suffix:

    *Controllers*: `BlogBundle:Post:show` -> `Blog:Post:show`

    *Templates*:   `BlogBundle:Post:show.html.twig` -> `Blog:Post:show.html.twig`

    *Resources*:   `@BlogBundle/Resources/config/blog.xml` -> `@Blog/Resources/config/blog.xml`

    *Doctrine*:    `$em->find('BlogBundle:Post', $id)` -> `$em->find('Blog:Post', $id)`

* Assetic filters must be now explicitly loaded:

        assetic:
            filters:
                cssrewrite: ~
                yui_css:
                    jar:      "/path/to/yuicompressor.jar"
                my_filter:
                    resource: "%kernel.root_dir%/config/my_filter.xml"
                    foo:      bar
