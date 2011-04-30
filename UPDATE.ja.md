プロジェクトをアップデートする方法
==================================

このドキュメントでは、Symfony2 PRの特定のバージョンから1つ次のバージョンへアップデートする方法を説明します。
このドキュメントでは、フレームワークの "パブリックな" APIを使っている場合に必要な変更点についてのみ説明しています。
フレームワークのコアコードを "ハック" している場合は、変更履歴を注意深く追跡する必要があるでしょう。

PR12 から beta1
---------------

* CSRF シークレットの設定は、\ `secret` という必須のグローバル設定に変更されました（また、このシークレット値は CSRF 以外でも利用されます）

    変更前:

        framework:
            csrf_protection:
                secret: S3cr3t

    変更後:

        framework:
            secret: S3cr3t

* `File::getWebPath()` メソッドと `File::rename()` メソッドは削除されました。同様に `framework.document_root` コンフィギュレーションも削除されました。

* `session` のコンフィギュレーションがリファクタリングされました

  * `class` オプションが削除されました（代わりに `session.class` パラメータを使ってください）

  * PDO セッションストレージのコンフィギュレーションが削除されました（クックブックのレシピは修正中です）

  * `storage_id` オプションには、サービスIDの一部ではなく、サービスIDそのものを指定するように変更されました。

* `DoctrineMigrationsBundle` と `DoctrineFixturesBundle` の 2 つのバンドルは、symfony コアから独立し、個別のリポジトリで管理されるようになりました。

* フォームフレームワークの大きなリファクタリングが行われました（詳細はドキュメントを参照してください）

* `trans` タグで、翻訳するメッセージを引数として受け取る形式が廃止されました:

    {% trans "foo" %}
    {% trans foo %}

  次のような長い形式か、フィルタ形式を使ってください:

    {% trans %}foo{% endtrans %}
    {{ foo|trans }}

  こうすることで、タグとフィルタの使用方法が明確になり、自動出力エスケープのルールが適用された場合により分かりやすくなります（詳細はドキュメントを参照してください）。

* DependencyInjection コンポーネントの ContainerBuilder クラスと Definition クラスのいくつかのメソッドの名前が、より分かりやすく一貫性のある名前に変更されました:

  変更前:

        $container->remove('my_definition');
        $definition->setArgument(0, 'foo');

  変更後:

        $container->removeDefinition('my_definition');
        $definition->replaceArgument(0, 'foo');

* rememberme のコンフィギュレーションで、\ `token_provider key` サービスIDのサフィックスを指定するのではなく、サービスIDそのものを指定するように変更されました。

PR11 から PR12
--------------

* HttpFoundation\Cookie::getExpire() は getExpiresTime() に名前が変更されました。

* XMLのコンフィギュレーションの記述方法が変更されました。属性が1つしかないタグは、すべてタグのコンテンツとして記述するように変更されました。

  変更前:

        <bundle name="MyBundle" />
        <app:engine id="twig" />
        <twig:extension id="twig.extension.debug" />

  変更後:

        <bundle>MyBundle</bundle>
        <app:engine>twig</app:engine>
        <twig:extension>twig.extension.debug</twig:extension>

* SwitchUserListenerが有効な場合に、すべてのユーザーが任意のアカウントになりすませる致命的な脆弱性を修正しました。SwitchUserListenerを利用しない設定にしている場合は影響はありません。

* DIコンテナのコンパイルプロセスの最後に、すべてのサービスに対する参照のバリデーションがより厳密に行われるようになりました。これにより、無効なサービス参照が見つかった場合は、コンパイル時の例外が発生します（以前の動作は、実行時例外でした）。

PR10 から PR11
--------------

* エクステンションのコンフィギュレーションクラスには、\ `Symfony\Component\Config\Definition\ConfigurationInterface`\ インターフェイスを実装する必要があります。この部分の後方互換性は維持されていますが、今後の開発のために、エクステンションにこのインターフェイスを実装しておいてください。

* Monologのオプション "fingerscrossed" は "fingers_crossed" に名前が変更されました。

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
