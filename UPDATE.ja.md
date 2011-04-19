プロジェクトをアップデートする方法
==================================

このドキュメントでは、Symfony2 PRの特定のバージョンから1つ次のバージョンへアップデートする方法を説明します。
このドキュメントでは、フレームワークの "パブリックな" APIを使っている場合に必要な変更点についてのみ説明しています。
フレームワークのコアコードを "ハック" している場合は、変更履歴を注意深く追跡する必要があるでしょう。

PR10 から PR11
--------------

* エクステンションのコンフィギュレーションクラスには、\ `Symfony\Component\Config\Definition\ConfigurationInterface`\ インターフェイスを実装する必要があります。この部分の後方互換性は維持されていますが、今後の開発のために、エクステンションにこのインターフェイスを実装しておいてください。

PR9 から PR10
-------------

* バンドルの論理名には、再び `Bundle` サフィックスを付けるように修正されました:

    *コントローラ*: `Blog:Post:show` -> `BlogBundle:Post:show`

    *テンプレート*: `Blog:Post:show.html.twig` -> `BlogBundle:Post:show.html.twig`

    *リソース*:     `@Blog/Resources/config/blog.xml` -> `@BlogBundle/Resources/config/blog.xml`

    *Doctrine*:     `$em->find('Blog:Post', $id)` -> `$em->find('BlogBundle:Post', $id)`

* `ZendBundle` は `MonologBundle` に置き換えられました。
  これに関するプロジェクトのアップデート方法は、Symfony Standard Edition の変更点を参考にしてください:
  https://github.com/symfony/symfony-standard/pull/30/files

* コアバンドルのパラメータは、ほぼすべて削除されました。
  代わりにバンドルのエクステンションの設定で公開されている設定を使うようにしてください。

* 一貫性のために、いくつかのコアバンドルのサービス名が変更されました。

* バリデータの名前空間が `validation` から `assert` へ変更されました（PR9向けにアナウンスされていましたが、PR10での変更となりました）:

    変更前:

        @validation:NotNull

    変更後:

        @assert:NotNull

    さらに、いくつかの制約で使われていた `Assert` プレフィックスは削除されました(`AssertTrue` から `True` へ変更)

* `ApplicationTester::getDisplay()` と `CommandTester::getDisplay()` メソッドは、コマンドの終了コードを返すようになりました


PR8 から PR9
------------

* `Symfony\Bundle\FrameworkBundle\Util\Filesystem` は、`Symfony\Component\HttpKernel\Util\Filesystem` へ移動されました

* `Execute` 制約は、`Callback` 制約に名前が変更されました

* HTTPの例外クラスのシグニチャが変更されました:

    変更前:

        throw new NotFoundHttpException('Not Found', $message, 0, $e);

    変更後:

        throw new NotFoundHttpException($message, $e);

* RequestMatcher クラスでは、正規表現に `^` と `$` が自動的には追加されなくなりました

    この変更によって、セキュリティの設定をたとえば次のように変更する必要があります:

    変更前:

        pattern:  /_profiler.*
        pattern:  /login

    変更後:

        pattern:  ^/_profiler
        pattern:  ^/login$

* `app/` ディレクトリ以下のグローバルテンプレートの位置が変更されました(古いディレクトリでは動作しなくなります):

    変更前:

        app/views/base.html.twig
        app/views/AcmeDemoBundle/base.html.twig

    変更後:

        app/Resources/views/base.html.twig
        app/Resources/AcmeDemo/views/base.html.twig

* バンドルの論理名に、`Bundle` サフィックスをつける必要がなくなりました:

    *コントローラ*: `BlogBundle:Post:show` -> `Blog:Post:show`

    *テンプレート*: `BlogBundle:Post:show.html.twig` -> `Blog:Post:show.html.twig`

    *リソース*:     `@BlogBundle/Resources/config/blog.xml` -> `@Blog/Resources/config/blog.xml`

    *Doctrine*:    `$em->find('BlogBundle:Post', $id)` -> `$em->find('Blog:Post', $id)`

* Asseticのフィルターは明示的にロードする必要があります:

    assetic:
        filters:
            cssrewrite: ~
            yui_css:
                jar: "/path/to/yuicompressor.jar"
            my_filter:
                resource: "%kernel.root_dir%/config/my_filter.xml"
                foo:      bar
