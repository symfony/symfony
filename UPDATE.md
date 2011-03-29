How to update your project?
===========================

This document explains how to upgrade from one Symfony2 PR version to the next
one. It only discusses changes that need to be done when using the "public"
API of the framework. If you "hack" the core, you should probably follow the
timeline closely anyway.

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

        profiler:
            pattern:  /_profiler/.*

    After:

        profiler:
            pattern:  ^/_profiler

* Global templates under `app/` moved to a new location (old directory did not
  work anyway):

    Before:

        app/views/base.html.twig
        app/views/AcmeDemoBundle/base.html.twig

    After:

        app/Resources/views/base.html.twig
        app/Resources/AcmeDemo/views/base.html.twig

* Namespace for validators has changed from `validation` to `assert`:

    Before:

        @validation:NotNull

    After:

        @assert:NotNull

    Moreover, the `Assert` prefix used for some constraints has been removed
    (`AssertTrue` to `True`).

* Bundle logical names lose their `Bundle` suffix:

    *Controllers*: `BlogBundle:Post:show` -> `Blog:Post:show`

    *Templates*:   `BlogBundle:Post:show.html.twig` -> `Blog:Post:show.html.twig`

    *Resources*:   `@BlogBundle/Resources/config/blog.xml` -> `@Blog/Resources/config/blog.xml`

    *Doctrine*:    `$em->find('BlogBundle:Post', $id)` -> `$em->find('Blog:Post', $id)`
