How to update your project?
===========================

This document explains how to upgrade from one Symfony2 PR version to the next
one. It only discusses changes that need to be done when using the "public"
API of the framework. If you "hack" the core, you should probably follow the
timeline closely anyway.

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
