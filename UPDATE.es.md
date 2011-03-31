¿Cómo actualizar tu proyecto?
=============================

Este documento explica cómo actualizar de una versión PR de Symfony2 a la siguiente. Solamente se explican los cambios que tienes que hacer cuando utilizas la API pública del framework. Si has *hackeado* alguna parte interna del núcleo del framework, es mejor que sigas con atención los cambios que se realizan cada día en el repositorio.

De PR8 a PR9
------------

* `Symfony\Bundle\FrameworkBundle\Util\Filesystem` se ha movido a
  `Symfony\Component\HttpKernel\Util\Filesystem`

* La restricción o *constraint* `Execute` se ha renombrado a `Callback`

* Han cambiado los argumentos de las clases de las excepciones HTTP:

    Antes:

        throw new NotFoundHttpException('Not Found', $message, 0, $e);

    Ahora:

        throw new NotFoundHttpException($message, $e);

* La clase RequestMatcher ya no añade `^` y `$` en las expresiones regulares.

    Así que tienes que actualizar por ejemplo la configuración de la seguridad:

    Antes:

        profiler:
            pattern:  /_profiler/.*

    Ahora:

        profiler:
            pattern:  ^/_profiler

* Las plantillas globales del directorio `app/` se han movido a otro sitio (de todas formas en el directorio anterior tampoco funcionaban)::

    Antes:

        app/views/base.html.twig
        app/views/AcmeDemoBundle/base.html.twig

    Ahora:

        app/Resources/views/base.html.twig
        app/Resources/AcmeDemo/views/base.html.twig

* El namespace de los validadores ha cambiado de `validation` a `assert`:

    Antes:

        @validation:NotNull

    Ahora:

        @assert:NotNull

    Además, el prefijo `Assert` que utilizaban algunas restricciones o *constraints* se ha eliminado (`AssertTrue` ahora es `True`).

* El nombre lógico de los *bundles* ya no incluye el sufijo `Bundle`:

    *Controladores*: `BlogBundle:Post:show` -> `Blog:Post:show`

    *Plantillas*:   `BlogBundle:Post:show.html.twig` -> `Blog:Post:show.html.twig`

    *Recursos*:   `@BlogBundle/Resources/config/blog.xml` -> `@Blog/Resources/config/blog.xml`

    *Doctrine*:    `$em->find('BlogBundle:Post', $id)` -> `$em->find('Blog:Post', $id)`

