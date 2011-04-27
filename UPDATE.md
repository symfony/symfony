How to update your project?
===========================

This document explains how to upgrade from one Symfony2 PR version to the next
one. It only discusses changes that need to be done when using the "public"
API of the framework. If you "hack" the core, you should probably follow the
timeline closely anyway.

beta1 to beta2
--------------

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

* The `doctrine.orm.entity_managers` is now hash of entity manager names/ids pairs:

    Before: array('default', 'foo')
    After:  array('default' => 'doctrine.orm.default_entity_manager', 'foo' => 'doctrine.orm.foo_entity_manager'))

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

* Some methods in the DependencyInjection component's ContainerBuilder and
  Definition classes have been renamed to be more specific and consistent:

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

* HttpFoundation\Cookie::getExpire() was renamed to getExpiresTime()

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

* The "fingerscrossed" Monolog option has been renamed to "fingers_crossed".

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
                jar: "/path/to/yuicompressor.jar"
            my_filter:
                resource: "%kernel.root_dir%/config/my_filter.xml"
                foo:      bar
