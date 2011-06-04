How to update your project?
===========================

This document explains how to upgrade from one Symfony2 PR version to the next
one. It only discusses changes that need to be done when using the "public"
API of the framework. If you "hack" the core, you should probably follow the
timeline closely anyway.

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