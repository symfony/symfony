Como atualizar seu projeto?
===========================

Este documento explica como realizar a atualização de uma versão PR do Symfony
para a próxima. Somente são discutidas mudanças que devem ser feitas quando se
usa a versão "pública" da API do framework. Se você alterar o "core", é melhor
acompanhar a linha do tempo com mais cuidado.

PR8 para PR9
----------

* `Symfony\Bundle\FrameworkBundle\Util\Filesystem` foi movido para
  `Symfony\Component\HttpKernel\Util\Filesystem`

* A restrição `Execute` foi renomeada para `Callback`

* A assinatura das classes de Exceções HTTP mudaram:

    Antes:

        throw new NotFoundHttpException('Not Found', $message, 0, $e);

    Depois:

        throw new NotFoundHttpException($message, $e);

* A classe `RequestMatcher` não mais adiciona `^` e `$` nas expressões regulares.

    Você deve alterar suas configurações de segurança de acordo, por exemplo:

    Antes:

        profiler:
            pattern:  /_profiler/.*

    Depois:

        profiler:
            pattern:  ^/_profiler

* Templates globais em `app/` foram movidos para uma nova localidade (a pasta 
  antiga não funcionava de qualquer forma):

    Antes:

        app/views/base.html.twig
        app/views/AcmeDemoBundle/base.html.twig

    Depois:

        app/Resources/views/base.html.twig
        app/Resources/AcmeDemo/views/base.html.twig

* O namespace para os validadores foi alterado de `validation` para `assert`:

    Antes:

        @validation:NotNull

    Depois:

        @assert:NotNull

    Além disso, o prefixo `Assert` usado em algumas confirmações foi removido
    (`AssertTrue` para `True`).

* Os nome lógicos dos Bundles perderam o suffixo `Bundle`:

    *Controllers*: `BlogBundle:Post:show` -> `Blog:Post:show`

    *Templates*:   `BlogBundle:Post:show.html.twig` -> `Blog:Post:show.html.twig`

    *Resources*:   `@BlogBundle/Resources/config/blog.xml` -> `@Blog/Resources/config/blog.xml`

    *Doctrine*:    `$em->find('BlogBundle:Post', $id)` -> `$em->find('Blog:Post', $id)`
