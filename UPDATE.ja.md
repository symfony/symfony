プロジェクトをアップデートする方法
==================================

このドキュメントでは、Symfony2 の特定のバージョンから1つ次のバージョンへアップデートする方法を説明します。
このドキュメントでは、フレームワークの "パブリックな" APIを使っている場合に必要な変更点についてのみ説明しています。
フレームワークのコアコードを "ハック" している場合は、変更履歴を注意深く追跡する必要があるでしょう。

RC4 から RC5
------------

* `MapFileClassLoader` は削除され `MapClassLoader` が採用されました。

* `exception_controller` の設定は、 `framework` セクションの `twig` へ移動しました。

* カスタムエラーページは、現在 `TwigBundle` の代わりに `FrameworkBundle` を参照する必要があります。(参照 http://symfony.com/doc/2.0/cookbook/controller/error_pages.html)

* `EntityUserProvider` クラスは Bridge へ移動されました。
  FQCN は `Symfony\Component\Security\Core\User\EntityUserProvider` から
  `Symfony\Bridge\Doctrine\Security\User\EntityUserProvider` に変更になります。

* `HeaderBag` からの Cookie アクセスが削除されました。
  リクエスト Cookie へのアクセスには、`Request::$cookies` を使ってください。

* `ResponseHeaderBag::getCookie()` メソッドと `ResponseHeaderBag::hasCookie()` メソッドは削除されました。

* `ResponseHeaderBag::getCookies()` メソッドの引数で、戻り値のフォーマットを指定できるようになりました。指定できる値は `ResponseHeaderBag::COOKIES_FLAT` (デフォルト値) または `ResponseHeaderBag::COOKIES_ARRAY` です。

    * `ResponseHeaderBag::COOKIES_FLAT` を指定すると、戻り値は単純な配列になります（配列のキーは、Cookie の名前ではなくなります）:

        * array(0 => `Cookie インスタンス`, 1 => `別の Cookie インスタンス`)

    * `ResponseHeaderBag::COOKIES_ARRAY` を指定すると、戻り値は多次元配列になります:

        * array(`ドメイン` => array(`パス` => array(`Cookie 名` => `Cookie インスタンス`)))

* 制約は有効となったキーのみを保持し、その値は保持していないため、Choice 制約の推測クラス（Guesser）は削除されました。

* MonologBundle の設定のリファクタリングが行われました。

    * プロセッサでサポートされるのは、サービスのみです。このサービスは `monolog.processor` タグを使って登録します。次の 3 つの属性を指定できます:

        * `handler`: 特定のハンドラーのみに対して登録する場合、そのハンドラーの名前
        * `channel`: 特定のロギングチャンネルのみに対して登録する場合のチャンネル (`handler` とどちらか一方のみを指定)
        * `method`: レコードの処理に使用するメソッド (指定しない場合は `__invoke` が使われます)

    * `SwiftMailerHandler` の email_prototype 設定に指定できるのは、サービスのみです。

        * 変更前:

            email_prototype: @acme_demo.monolog.email_prototype

        * 変更後:

            email_prototype: acme_demo.monolog.email_prototype

          もしくは、次のようにしてプロトタイプ用のファクトリを使うこともできます:

            email_prototype:
                id:     acme_demo.monolog.email_prototype
                method: getPrototype

* セキュリティを考慮し、プロキシ由来の HTTP ヘッダー (`HTTP_X_FORWARDED_FOR`、`X_FORWARDED_PROTO`、`X_FORWARDED_HOST` 等) は、デフォルトでは信頼されなくなりました。リバースプロキシ経由でアプリケーションを利用する構成の場合は、次のように設定してください:

        framework:
            trust_proxy_headers: true

* 意図しない名前の衝突を避けるため、AbstractType によるフォームタイプ名の自動定義は行われなくなりました。カスタムタイプを作成する場合は、明示的に `getName()` メソッドを実装する必要があります。

RC3 から RC4
------------

* Annotation クラスには、@Annotation を付加してください。
  (例については Validator コンポーネントの制約クラスを参照してください)

* アノテーションのオートロードには、PHP の機構ではなく独自の機構が使われるように変更されました。
  これにより、失敗の状態についてより制御できるようになりました。
  コードを動作させるようにするには、`autoload.php` ファイルの末尾に次のコードを追加してください:

        use Doctrine\Common\Annotations\AnnotationRegistry;

        AnnotationRegistry::registerLoader(function($class) use ($loader) {
            $loader->loadClass($class);
            return class_exists($class, false);
        });

        AnnotationRegistry::registerFile(
            __DIR__.'/../vendor/doctrine/lib/Doctrine/ORM/Mapping/Driver/DoctrineAnnotations.php'
        );

  `$loader` 変数は `UniversalClassLoader` のインスタンスです。
  また、ORM のパスを `DoctrineAnnotations.php` に変更しなければいけない場合もあります。
  `UniversalClassLoader` を使っていない場合、アノテーションの登録の詳細については、[Doctrine アノテーションドキュメント](http://www.doctrine-project.org/docs/common/2.1/en/reference/annotations.html) を参照してください。

beta5 から RC1
--------------

* `Symfony\Bundle\FrameworkBundle\Command\Command` クラスの名前が
  `Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand` に変更されました。

* ルーティングの `AnnotGlobLoader` クラスが削除されました。

* Twig フォームテンプレートのいくつかのブロックの名前は、衝突を避けるために変更されました。

    * `container_attributes` から `widget_container_attributes`
    * `attributes` から `widget_attributes`
    * `options` から `widget_choice_options`

* イベントの変更:

    * すべてのリスナーには、`kernel.listener` タグではなく `kernel.event_listener` タグを設定する必要があります。
    * カーネルイベントのプレフィックスが `core` から `kernel` に変更されました:

        * 変更前:

                <tag name="kernel.listener" event="core.request" method="onCoreRequest" />

        * 変更後:

                <tag name="kernel.event_listener" event="kernel.request" method="onKernelRequest" />

        Note: メソッド名 method 属性で独立して指定できるので、`onCoreRequest` のままでも動作しますが、将来的な一貫性のためにイベント名に合わせたメソッド名に変更しておく方がよいでしょう。

    * `Symfony\Component\HttpKernel\CoreEvents` クラスの名前が
      `Symfony\Component\HttpKernel\KernelEvents` に変更されました。

* `TrueValidator` と `FalseValidator` の受け付ける値をより限定しました。

beta4 から beta5
----------------

* `UserProviderInterface::loadUser()` メソッドの名前は、メソッドの目的がより明確になるよう、`UserProviderInterface::refreshUser()` に変更されました。

* `WebTestCase` クラスの `$kernel` プロパティは static に変更されました。
  ファンクショナルテスト内で `$this->kernel` を使っている箇所は、`self::$kernel` に変更してください。

* AsseticBundle は独立したリポジトリで管理されるようになりました（Symfony2 Standard Edition にはバンドルされています）。

* Yaml コンポーネントの変更:

    * Exception クラスは独自の名前空間へ移動されました。
    * `Yaml::load()` メソッドの名前は `Yaml::parse()` に変更されました。

* `HttpFoundation` コンポーネントの `File` クラスのリファクタリング:

    * `Symfony\Component\HttpFoundation\File\File` の API が新しくなりました。

       * `\SplFileInfo` を継承するようになりました

           * `getName()` は `getBasename()` に変更
           * `getDirectory()` は `getPath()` に変更
           * `getPath()` は `getRealPath()` に変更

       * `move()` メソッドを呼び出した時に、対象ディレクトリがまだ存在していない場合は作成されるようになりました。

       * `getExtension()` と `guessExtension()` の戻り値の拡張子から、先頭の `.` が除去されるように変更されました。

    * `Symfony\Component\HttpFoundation\File\UploadedFile` の API が新しくなりました。

        * コンストラクタに真偽値の引数が追加されました。
          この引数に true を指定すると、ファイルを移動できるようになりますが、テストモード以外では true に設定しないでください。
          コアファイル以外の外部から true に設定することは想定していません。

        * `getMimeType()` は、対象ファイルの MIME タイプを必ず返すように変更されました。
           リクエストから MIME タイプを取得する場合は、`getClientMimeType()` メソッドを使ってください。

        * `getSize()` は、対象ファイルのサイズを必ず返すように変更されました。
           リクエストからファイルサイズを取得する場合は、`getClientSize()` メソッドを使ってください。

        * リクエストからオリジナルのファイル名を取得する場合は、`getClientOriginalName()` メソッドを使ってください。

* Twig の `extensions` 設定は削除されました。
  Twig エクステンションを登録する場合は、`twig.extension` タグを使ってください。

* Monolog ハンドラのスタックで、デフォルトで記録が伝播されるようになりました。
  伝播されないようにするには、bubble を明示的に false に設定してください。

* `SerializerInterface` が拡張されました。
  Serializer クラスのパブリックメソッドの数は減りましたが、後方互換性が損なわれ、コンポーネント独自の Exception クラスが追加されました。

* `FileType` フォームクラスが大きく変更されました。

    * テンポラリストレージが削除されました。

    * FileType の `type` オプションが削除されました。
      新しい動作は、以前の `type` に `file` を設定した場合の動作と同じです。

    * ファイルウィジェットは、他の INPUT フィールドと同じようにレンダリングされるように変更されました。

* Doctrine の `EntityType` クラスコンストラクタの `em` 引数には、EntityManager インスタンスの代わりにエンティティマネージャー名を指定するよう変更されました。
  このオプションをを渡さない場合、以前と同じようにデフォルトのエンティティマネージャーが使われます。

* Console コンポーネントの中の `Command::getFullname()` メソッドと `Command::getNamespace()` メソッドは削除されました
  (`Command::getName()` メソッドの振る舞いは以前の `Command::getFullname()` メソッドと同じになりました)。

* デフォルトの Twig フォームテンプレートは Twig bridge に移動されました。以下のようにすればテンプレートや
  コンフィギュレーション設定中で現在Twig フォームテンプレートを参照できます:

    変更前:

        TwigBundle:Form:div_layout.html.twig

    変更後:

        form_div_layout.html.twig

* キャッシュウォーマーに関連する設定は、すべて削除されました。

* `Response::isRedirected()` メソッドは `Response::isRedirect()` メソッドに統合されました。

beta3 から beta4
----------------

* `Profile` のインスタンスを返す `Client::getProfile()` メソッドへの変更に従い、`Client::getProfiler()` メソッドは削除されました。

* いくつかの `UniversalClassLoader` のメソッド名は変更されました:

    * `registerPrefixFallback` から `registerPrefixFallbacks`
    * `registerNamespaceFallback` から `registerNamespaceFallbacks`

* イベントシステムはさらに柔軟になりました。リスナーは任意の有効でコール可能な PHP 関数であれば可能になりました。

    * `EventDispatcher::addListener($eventName, $listener, $priority = 0)`:
        * `$eventName` がイベント名で (もう配列ではいけません)、
        * `$listener` が コール可能な PHP 関数です。

    * イベントクラス名と定数が変更されました:

        * 以前の `Symfony\Component\Form\Events` のクラス名と定数:

                Events::preBind = 'preBind'
                Events::postBind = 'postBind'
                Events::preSetData = 'preSetData'
                Events::postSetData = 'postSetData'
                Events::onBindClientData = 'onBindClientData'
                Events::onBindNormData = 'onBindNormData'
                Events::onSetData = 'onSetData'

        * 新しい `Symfony\Component\Form\FormEvents` クラス名と定数:

                FormEvents::PRE_BIND = 'form.pre_bind'
                FormEvents::POST_BIND = 'form.post_bind'
                FormEvents::PRE_SET_DATA = 'form.pre_set_data'
                FormEvents::POST_SET_DATA = 'form.post_set_data'
                FormEvents::BIND_CLIENT_DATA = 'form.bind_client_data'
                FormEvents::BIND_NORM_DATA = 'form.bind_norm_data'
                FormEvents::SET_DATA = 'form.set_data'

        * 以前の `Symfony\Component\HttpKernel\Events` のクラス名と定数:

                Events::onCoreRequest = 'onCoreRequest'
                Events::onCoreException = 'onCoreException'
                Events::onCoreView = 'onCoreView'
                Events::onCoreController = 'onCoreController'
                Events::onCoreResponse = 'onCoreResponse'

        * 新しい `Symfony\Component\HttpKernel\CoreEvents` のクラス名と定数:

                CoreEvents::REQUEST = 'core.request'
                CoreEvents::EXCEPTION = 'core.exception'
                CoreEvents::VIEW = 'core.view'
                CoreEvents::CONTROLLER = 'core.controller'
                CoreEvents::RESPONSE = 'core.response'

        * 以前の `Symfony\Component\Security\Http\Events` のクラス名と定数:

                Events::onSecurityInteractiveLogin = 'onSecurityInteractiveLogin'
                Events::onSecuritySwitchUser = 'onSecuritySwitchUser'

        * 新しい `Symfony\Component\Security\Http\SecurityEvents` のクラス名と定数:

                SecurityEvents::INTERACTIVE_LOGIN = 'security.interactive_login'
                SecurityEvents::SWITCH_USER = 'security.switch_user'

    * `addListenerService` は第 1 引数として単一のイベント名だけを取るようになりました。

    * コンフィギュレーションのタグでは、呼び出すメソッドを指定する必要があります。

        * 変更前:

                <tag name="kernel.listener" event="onCoreRequest" />

        * 変更後:

                <tag name="kernel.listener" event="core.request" method="onCoreRequest" />

    * Subscriber は常に連想配列を返すようになりました:

        * 変更前:

                public static function getSubscribedEvents()
                {
                    return Events::onBindNormData;
                }

        * 変更後:

                public static function getSubscribedEvents()
                {
                    return array(FormEvents::BIND_NORM_DATA => 'onBindNormData');
                }

* フォーム `DateType` パラメーターの `single-text` は `single_text` へ変更されました
* フォームフィールドラベルヘルパーは属性の設定も受け入れるようになりました。例 :

```html+jinja
{{ form_label(form.name, 'Custom label', { 'attr': {'class': 'name_field'} }) }}
```

* Swiftmailer を使うためには、autoloader ("app/autoloader.php") を通して "init.php" を登録し、
  `Swift_` prefix の登録を autoloader から削除しなければなりません。これをどのように行うべきかの例は、
  Standard Distribution をご覧ください。
  [autoload.php](https://github.com/symfony/symfony-standard/blob/v2.0.0BETA4/app/autoload.php#L29).

beta2 から beta3
----------------

* `framework.annotations` に属する設定が少し変更されました。

    変更前:

        framework:
            annotations:
                cache: file
                file_cache:
                    debug: true
                    dir: /foo

    変更後:

        framework:
            annotations:
                cache: file
                debug: true
                file_cache_dir: /foo

beta1 から beta2
----------------

* アノテーションのパース処理が変更され、Doctrine Common 3.0 を利用するようになりました。
  クラス内で使うアノテーションは、インポートする必要があります（`use` で PHP の名前空間をインポートするのと同様です）。

  変更前:

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
  変更後:

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

* `Set` 制約の記述が変更され、必要なくなったため削除されました。

変更前:

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
変更後:

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

* `framework.validation.annotations` に属するコンフィギュレーションは削除され、`framework.validation.enable_annotations` の真偽値に置き換えられました（デフォルトでは `false` です）。

* フォームを使う場合は、明示的に有効化するよう変更されました（Symfony Standard Edition のコンフィギュレーションではデフォルトで有効に設定されています）。

        framework:
            form: ~

    これは、次のように記述しても同じです。

        framework:
            form:
                enabled: true

* Routing コンポーネントの例外を移動しました。

    変更前:

        Symfony\Component\Routing\Matcher\Exception\Exception
        Symfony\Component\Routing\Matcher\Exception\NotFoundException
        Symfony\Component\Routing\Matcher\Exception\MethodNotAllowedException

    変更後:

        Symfony\Component\Routing\Exception\Exception
        Symfony\Component\Routing\Exception\NotFoundException
        Symfony\Component\Routing\Exception\MethodNotAllowedException

* Form コンポーネントの `csrf_page_id` オプションの名前は、`intention` に変更されました。

* `error_handler` の設定が削除されました。`ErrorHandler` クラスは Symfony Standard Edition の `AppKernel` で直接管理されるように変更されました。

* Doctrine のメタデータ用のディレクトリが、`Resources/config/doctrine/metadata/orm/` から `Resources/config/doctrine` に変更され、各ファイルの拡張子が `.dcm.yml` から ``.orm.yml`` に変更されました。
  また、ファイル名は短いクラス名のみに変更されました。

    変更前:

        Resources/config/doctrine/metadata/orm/Bundle.Entity.dcm.xml
        Resources/config/doctrine/metadata/orm/Bundle.Entity.dcm.yml

    変更後:

        Resources/config/doctrine/Entity.orm.xml
        Resources/config/doctrine/Entity.orm.yml

* 新しい Doctrine Registry クラスの導入により、次のパラメータは削除されました（`doctrine` サービスのメソッドに置き換えられました）。

   * `doctrine.orm.entity_managers`
   * `doctrine.orm.default_entity_manager`
   * `doctrine.dbal.default_connection`

    変更前:

        $container->getParameter('doctrine.orm.entity_managers')
        $container->getParameter('doctrine.orm.default_entity_manager')
        $container->getParameter('doctrine.orm.default_connection')

    変更後:

        $container->get('doctrine')->getEntityManagerNames()
        $container->get('doctrine')->getDefaultEntityManagerName()
        $container->get('doctrine')->getDefaultConnectionName()

    ただし、これらのメソッドを使わなくても、次のようにして Registry オブジェクトから直接 EntityManager オブジェクトを取得できます。

    変更前:

        $em = $this->get('doctrine.orm.entity_manager');
        $em = $this->get('doctrine.orm.foobar_entity_manager');

    変更後:

        $em = $this->get('doctrine')->getEntityManager();
        $em = $this->get('doctrine')->getEntityManager('foobar');

* `doctrine:generate:entities` コマンドの引数とオプションが変更されました。
  新しい引数とオプションの詳細は、`./app/console doctrine:generate:entities --help` コマンドを実行して確認してください。

* `doctrine:generate:repositories` コマンドは削除されました。
  このコマンドに相当する機能は、`doctrine:generate:entities` コマンドに統合されました。

* Doctrine イベントサブスクライバーは、ユニークな `doctrine.event_subscriber` タグを使うように変更されました。
  また、Doctrine イベントリスナーは、ユニークな `doctrine.event_listener` タグを使うように変更されました。
  コネクションを指定するには、オプションの `connection` 属性を使ってください。

    変更前:

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

    変更後:

        listener:
            class: MyEventListener
            tags:
                - { name: doctrine.event_listener, event: name }                      # すべてのコネクションに対して登録
                - { name: doctrine.event_listener, event: name, connection: default } # デフォルトコネクションにのみ登録
        subscriber:
            class: MyEventSubscriber
            tags:
                - { name: doctrine.event_subscriber }                      # すべてのコネクションに対して登録
                - { name: doctrine.event_subscriber, connection: default } # デフォルトコネクションにのみ登録

* アプリケーションの翻訳ファイルは、`Resources` ディレクトリに保存されるように変更されました。

    変更前:

        app/translations/catalogue.fr.xml

    変更後:

        app/Resources/translations/catalogue.fr.xml

* `collection` フォームタイプの `modifiable` オプションは、2 つのオプション "allow_add" と "allow_delete" に分割されました。

    変更前:

        $builder->add('tags', 'collection', array(
            'type' => 'text',
            'modifiable' => true,
        ));

    変更後:

        $builder->add('tags', 'collection', array(
            'type' => 'text',
            'allow_add' => true,
            'allow_delete' => true,
        ));

* `Request::hasSession()` メソッドの名前は `Request::hasPreviousSession()` に変更されました。`hasSession()` メソッドはまだ存在しますが、
  セッションが以前のリクエストから開始されたかどうかではなく、リクエストがセッションオブジェクトを含んでいるかチェックするのみです。

* Serializer: NormalizerInterface の `supports()` メソッドは `supportsNormalization()` と `supportsDenormalization()` の 2 つのメソッドに分割されました。

* `ParameterBag::getDeep()` メソッドは削除され、`ParameterBag::get()` メソッドの真偽値の引数に置き換えられました。

* Serializer: `AbstractEncoder` と `AbstractNormalizer` はそれぞれ `SerializerAwareEncoder` と `SerializerAwareNormalizer` に名前が変更されました。

* Serializer: すべてのインターフェイスから `$properties` という引数が除かれました。

* Form: オプションの値である "date" タイプの "widget" の "text" は "single-text" に名前が変更されました。
  "text" は現在は個々のテキストボックスを示します ("time" タイプのように) 。

* Form: ビュー変数 `name` が `full_name` に変更されました。`name` 変数には `$form->getName()` と同じ値である、ローカルの短い名前が格納されるようになりました。

PR12 から beta1
---------------

* CSRF シークレットの設定は、`secret` という必須のグローバル設定に変更されました（また、このシークレット値は CSRF 以外でも利用されます）

    変更前:

        framework:
            csrf_protection:
                secret: S3cr3t

    変更後:

        framework:
            secret: S3cr3t

* `File::getWebPath()` メソッドと `File::rename()` メソッドは削除されました。同様に `framework.document_root` コンフィギュレーションも削除されました。

* `File::getDefaultExtension()` メソッドの名前は `File::guessExtension()` に変更されました。
  また、拡張子を推測できなかった場合は null を返すように変更されました。

* `session` のコンフィギュレーションがリファクタリングされました

  * `class` オプションが削除されました（代わりに `session.class` パラメータを使ってください）

  * PDO セッションストレージのコンフィギュレーションが削除されました（クックブックのレシピは修正中です）

  * `storage_id` オプションには、サービスIDの一部ではなく、サービスIDそのものを指定するように変更されました。

* `DoctrineMigrationsBundle` と `DoctrineFixturesBundle` の 2 つのバンドルは、Symfony コアから独立し、個別のリポジトリで管理されるようになりました。

* フォームフレームワークの大きなリファクタリングが行われました（詳細はドキュメントを参照してください）

* `trans` タグで、翻訳するメッセージを引数として受け取る形式が廃止されました:

        {% trans "foo" %}
        {% trans foo %}

    次のような長い形式か、フィルタ形式を使ってください:

        {% trans %}foo{% endtrans %}
        {{ foo|trans }}

    こうすることで、タグとフィルタの使用方法が明確になり、自動出力エスケープのルールが適用された場合により分かりやすくなります（詳細はドキュメントを参照してください）。

* DependencyInjection コンポーネントの `ContainerBuilder` クラスと `Definition` クラスのいくつかのメソッドの名前が、より分かりやすく一貫性のある名前に変更されました:

    変更前:

        $container->remove('my_definition');
        $definition->setArgument(0, 'foo');

    変更後:

        $container->removeDefinition('my_definition');
        $definition->replaceArgument(0, 'foo');

* rememberme のコンフィギュレーションで、`token_provider key` サービスIDのサフィックスを指定するのではなく、サービスIDそのものを指定するように変更されました。

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

* エクステンションのコンフィギュレーションクラスには、`Symfony\Component\Config\Definition\ConfigurationInterface` インターフェイスを実装する必要があります。この部分の後方互換性は維持されていますが、今後の開発のために、エクステンションにこのインターフェイスを実装しておいてください。

* Monologのオプション `fingerscrossed` は `fingers_crossed` に名前が変更されました。

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
                    jar:      "/path/to/yuicompressor.jar"
                my_filter:
                    resource: "%kernel.root_dir%/config/my_filter.xml"
                    foo:      bar
