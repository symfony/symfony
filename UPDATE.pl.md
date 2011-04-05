Jak zaktualizować swój projekt?
===========================

Ten dokument wyjaśnia jak wykonać migrację pomiędzy starą a nową wersją 
Symfony 2 PR. Omawiane są tutaj tylko zmiany, które należy wprowadzić
w przypadku korzystania z publicznego API. Jeżeli programujesz rdzeń frameworka,
powinieneś śledzić dokładnie wszystkie zmiany.

PR10 do PR11
------------

* Klasy konfiguracji rozszerzeń powinny implementować interfejs
`Symfony\Component\Config\Definition\ConfigurationInterface`. Należy pamiętać że
kompatybilność binarna jest zachowana, jednak implementacja tego interfejsu
w Twoich rozszerzeniach pozwoli rozwijać je w przyszłości.

PR9 do PR10
-----------
* Logiczne nazwy Bundli odzyskały sufixy `Bundle`

    *Controllers*: `Blog:Post:show` -> `BlogBundle:Post:show`

    *Templates*:   `Blog:Post:show.html.twig` -> `BlogBundle:Post:show.html.twig`

    *Resources*:   `@Blog/Resources/config/blog.xml` -> `@BlogBundle/Resources/config/blog.xml`

    *Doctrine*:    `$em->find('Blog:Post', $id)` -> `$em->find('BlogBundle:Post', $id)`

* `ZendBundle` został zamieniony przez `MonologBundle`. Przejrzyj
  zmiany dokonane w Symfony SE, aby dowiedzieć się jak zaktualizować swój projekt:
  https://github.com/symfony/symfony-standard/pull/30/files
  
* Z niemal wszystkich wbudowanych bundli zostały usunięte parametry. 
  Zamiast ich powinieneś użyć ustawień dostarczanych przez konfigurację 
  rozszerzenia bundla.

* Dla lepszej spójności niektóre domyślne serwisy otrzymały nowe nazwy.

* Przestrzenie nazw dla walidatorów zostały zmienione z `validation` na `assert`
(zostało to ogłoszone przy PR9, jednak nie znalazło się tam):

    Przed:

        @validation:NotNull

    Po:

        @assert:NotNull

    Ponadto prefix `Assert` używany dla niektórych zmiennych został usunięty 
    (`AssertTrue` zmienił się na `True`).
	
* Metody `ApplicationTester::getDisplay()` i `CommandTester::getDisplay()`
  zwracają teraz komendę zakończenia parsowania kodu.

PR8 do PR9
----------

* `Symfony\Bundle\FrameworkBundle\Util\Filesystem` został przeniesiony do
  `Symfony\Component\HttpKernel\Util\Filesystem`

* Stała `Execute` została zmieniona na `Callback`

* Wywołanie klasy wyjątku HTTP zostało zmienione na:

    Przed:

        throw new NotFoundHttpException('Not Found', $message, 0, $e);

    Po:

        throw new NotFoundHttpException($message, $e);

* Klasa RequestMatcher nie potrzebuje już więcej  `^` i `$` dla regexpa.

    Musisz zaktualizować swoją konfigurację w sekcji security zgodnie z:

    Przed:

        profiler:
            pattern:  /_profiler/.*

    Po:

        profiler:
            pattern:  ^/_profiler
			
* Globalne szablony zostały przeniesione z `app/` do nowego miejsca (stary katalog 
nie jest już prawidłowy):		

    Przed:

        app/views/base.html.twig
        app/views/AcmeDemoBundle/base.html.twig

    Po:

        app/Resources/views/base.html.twig
        app/Resources/AcmeDemo/views/base.html.twig

* Logiczne nazwy bundli zostały pozbawione sufiksa `Bundle`:

    *Controllers*: `BlogBundle:Post:show` -> `Blog:Post:show`

    *Templates*:   `BlogBundle:Post:show.html.twig` -> `Blog:Post:show.html.twig`

    *Resources*:   `@BlogBundle/Resources/config/blog.xml` -> `@Blog/Resources/config/blog.xml`

    *Doctrine*:    `$em->find('BlogBundle:Post', $id)` -> `$em->find('Blog:Post', $id)`

* Filtry dla Assetic muszą być teraz ładowane w konfiguracji:

    assetic:
        filters:
            cssrewrite: ~
            yui_css:
                jar: "/ściezka/do/yuicompressor.jar"
            my_filter:
                resource: "%kernel.root_dir%/config/my_filter.xml"
                foo:      bar
