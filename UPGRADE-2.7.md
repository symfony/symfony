UPGRADE FROM 2.6 to 2.7
=======================

Global
------

 * Deprecation notices -
   `@trigger_error('... is deprecated ...', E_USER_DEPRECATED)` -
   are now triggered when using any deprecated functionality.

   By default these notices are silenced, so they won't appear in the PHP logs of
   your production server. However, these notices are still visible in the web
   debug toolbar, so you can know where your code needs an upgrade.

   In addition, it's strongly recommended to enable the [phpunit-bridge](https://github.com/symfony/phpunit-bridge)
   so that you can deal with deprecation notices in your test suite.

Router
------

 * Route conditions now support container parameters which
   can be injected into condition using `%parameter%` notation.
   Due to the fact that it works by replacing all parameters
   with their corresponding values before passing condition
   expression for compilation there can be BC breaks where you
   could already have used percentage symbols. Single percentage symbol
   usage is not affected in any way. Conflicts may occur where
   you might have used `%` as a modulo operator, here's an example:
   `foo%bar%2` which would be compiled to `$foo % $bar % 2` in 2.6
   but in 2.7 you would get an error if `bar` parameter
   doesn't exist or unexpected result otherwise.

 * The `getMatcherDumperInstance()` and `getGeneratorDumperInstance()` methods in the
   `Symfony\Component\Routing\Router` have been changed from `protected` to `public`.
   If you override these methods in a subclass, you will need to change your 
   methods to `public` as well. Note however that this is a temporary change needed for
   PHP 5.3 compatibility only. It will be reverted in Symfony 3.0.
 
Form
----

 * In form types and extension overriding the "setDefaultOptions" of the
   AbstractType or AbstractExtensionType has been deprecated in favor of
   overriding the new "configureOptions" method.

   The method "setDefaultOptions(OptionsResolverInterface $resolver)" will
   be renamed in Symfony 3.0 to "configureOptions(OptionsResolver $resolver)".

   Before:

   ```php
    use Symfony\Component\OptionsResolver\OptionsResolverInterface;

    class TaskType extends AbstractType
    {
        // ...
        public function setDefaultOptions(OptionsResolverInterface $resolver)
        {
            $resolver->setDefaults(array(
                'data_class' => 'AppBundle\Entity\Task',
            ));
        }
    }
   ```

   After:

   ```php
    use Symfony\Component\OptionsResolver\OptionsResolver;

    class TaskType extends AbstractType
    {
        // ...
        public function configureOptions(OptionsResolver $resolver)
        {
            $resolver->setDefaults(array(
                'data_class' => 'AppBundle\Entity\Task',
            ));
        }
    }
   ```

 * The "choice_list" option of ChoiceType was deprecated. You should use
   "choices_as_values" or "choice_loader" now.

   Before:

   ```php
   $form->add('status', 'choice', array(
       'choice_list' => new ObjectChoiceList(array(
           Status::getInstance(Status::ENABLED),
           Status::getInstance(Status::DISABLED),
           Status::getInstance(Status::IGNORED),
       )),
   ));
   ```

   After:

   ```php
   $form->add('status', 'choice', array(
       'choices' => array(
           Status::getInstance(Status::ENABLED),
           Status::getInstance(Status::DISABLED),
           Status::getInstance(Status::IGNORED),
       ),
       'choices_as_values' => true,
   ));
   ```

 * You should flip the keys and values of the "choices" option in ChoiceType
   and set the "choices_as_values" option to `true`. The default value of that
   option will be switched to `true` in Symfony 3.0.

   Before:

   ```php
   $form->add('status', 'choice', array(
       'choices' => array(
           Status::ENABLED => 'Enabled',
           Status::DISABLED => 'Disabled',
           Status::IGNORED => 'Ignored',
       )),
   ));
   ```

   After:

   ```php
   $form->add('status', 'choice', array(
       'choices' => array(
           'Enabled' => Status::ENABLED,
           'Disabled' => Status::DISABLED,
           'Ignored' => Status::IGNORED,
       ),
       'choices_as_values' => true,
       // important if you rely on your option value attribute (e.g. for JavaScript)
       // this will keep the same functionality as before
       'choice_value' => function ($choice) {
           return $choice;
       },
   ));
   ```

 * `Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface` was
   deprecated and will be removed in Symfony 3.0. You should use
   `Symfony\Component\Form\ChoiceList\ChoiceListInterface` instead.

   Before:

   ```php
   use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface;

   public function doSomething(ChoiceListInterface $choiceList)
   {
       // ...
   }
   ```

   After:

   ```php
   use Symfony\Component\Form\ChoiceList\ChoiceListInterface;

   public function doSomething(ChoiceListInterface $choiceList)
   {
       // ...
   }
   ```

 * `Symfony\Component\Form\Extension\Core\View\ChoiceView` was
   deprecated and will be removed in Symfony 3.0. You should use
   `Symfony\Component\Form\ChoiceList\View\ChoiceView` instead.
   The constructor arguments of the new class are in the same order than in the
   deprecated one (this was not true in 2.7.0 but has been fixed in 2.7.1).

 * `Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceList` was
   deprecated and will be removed in Symfony 3.0. You should use
   `Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory` instead.

   Before:

   ```php
   use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceList;

   $choiceList = new ChoiceList(
       array(Status::ENABLED, Status::DISABLED, Status::IGNORED),
       array('Enabled', 'Disabled', 'Ignored'),
       // Preferred choices
       array(Status::ENABLED),
   );
   ```

   After:

   ```php
   use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;

   $factory = new DefaultChoiceListFactory();

   $choices = array(Status::ENABLED, Status::DISABLED, Status::IGNORED);
   $labels = array('Enabled', 'Disabled', 'Ignored');

   $choiceList = $factory->createListFromChoices($choices);

   $choiceListView = $factory->createView(
       $choiceList,
       // Preferred choices
       array(Status::ENABLED),
       // Labels
       function ($choice, $key) use ($labels) {
           return $labels[$key];
       }
   );
   ```

 * `Symfony\Component\Form\Extension\Core\ChoiceList\LazyChoiceList` was
   deprecated and will be removed in Symfony 3.0. You should use
   `Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory::createListFromLoader()`
   together with an implementation of
   `Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface` instead.

   Before:

   ```php
   use Symfony\Component\Form\Extension\Core\ChoiceList\LazyChoiceList;

   class MyLazyChoiceList extends LazyChoiceList
   {
       public function loadChoiceList()
       {
           // load $choiceList

           return $choiceList;
       }
   }

   $choiceList = new MyLazyChoiceList();
   ```

   After:

   ```php
   use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
   use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

   class MyChoiceLoader implements ChoiceLoaderInterface
   {
       // ...
   }

   $factory = new DefaultChoiceListFactory();

   $choiceList = $factory->createListFromLoader(new MyChoiceLoader());
   ```

 * `Symfony\Component\Form\Extension\Core\ChoiceList\ObjectChoiceList` was
   deprecated and will be removed in Symfony 3.0. You should use
   `Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory` instead.

   Before:

   ```php
   use Symfony\Component\Form\Extension\Core\ChoiceList\ObjectChoiceList;

   $choiceList = new ObjectChoiceList(
       array(Status::getInstance(Status::ENABLED), Status::getInstance(Status::DISABLED)),
       // Label property
       'name'
   );
   ```

   After:

   ```php
   use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;

   $factory = new DefaultChoiceListFactory();

   $choiceList = $factory->createListFromChoices(array(
       Status::getInstance(Status::ENABLED),
       Status::getInstance(Status::DISABLED),
   ));

   $choiceListView = $factory->createView(
       $choiceList,
       // Preferred choices
       array(),
       // Label property
       'name'
   );
   ```

 * `Symfony\Component\Form\Extension\Core\ChoiceList\SimpleChoiceList` was
   deprecated and will be removed in Symfony 3.0. You should use
   `Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory` instead.

   Before:

   ```php
   use Symfony\Component\Form\Extension\Core\ChoiceList\SimpleChoiceList;

   $choiceList = new SimpleChoiceList(array(
       Status::ENABLED => 'Enabled',
       Status::DISABLED => 'Disabled',
   ));
   ```

   After:

   ```php
   use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;

   $factory = new DefaultChoiceListFactory();

   $choices = array(Status::ENABLED, Status::DISABLED);
   $labels = array('Enabled', 'Disabled');

   $choiceList = $factory->createListFromChoices($choices);

   $choiceListView = $factory->createView(
       $choiceList,
       // Preferred choices
       array(),
       // Label
       function ($choice, $key) use ($labels) {
           return $labels[$key];
       }
   );
   ```

 * The "property" option of `DoctrineType` was deprecated. You should use the
   new inherited option "choice_label" instead, which has the same effect.

   Before:

   ```php
   $form->add('tags', 'entity', array(
       'class' => 'Acme\Entity\MyTag',
       'property' => 'name',
   ))
   ```

   After:

   ```php
   $form->add('tags', 'entity', array(
       'class' => 'Acme\Entity\MyTag',
       'choice_label' => 'name',
   ))
   ```

 * The "loader" option of `DoctrineType` was deprecated and will be removed in
   Symfony 3.0. You should override the `getLoader()` method instead in a custom
   type.

   Before:

   ```php
   $form->add('tags', 'entity', array(
       'class' => 'Acme\Entity\MyTag',
       'loader' => new MyEntityLoader(),
   ))
   ```

   After:

   ```php
   class MyEntityType extends DoctrineType
   {
       // ...

       public function getLoader()
       {
           return new MyEntityLoader();
       }
   }
   ```

 * `Symfony\Bridge\Doctrine\Form\ChoiceList\EntityChoiceList` was
   deprecated and will be removed in Symfony 3.0. You should use
   `Symfony\Bridge\Doctrine\Form\ChoiceList\DoctrineChoiceLoader` instead.

   Before:

   ```php
   use Symfony\Component\Form\Extension\Core\ChoiceList\SimpleChoiceList;

   $choiceList = new EntityChoiceList($em, 'Acme\Entity\MyEntity');
   ```

   After:

   ```php
   use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;

   $factory = new DefaultChoiceListFactory();

   $choices = array(Status::ENABLED, Status::DISABLED);
   $labels = array('Enabled', 'Disabled');

   $choiceLoader = new DoctrineChoiceLoader($factory, $em, 'Acme\Entity\MyEntity');
   $choiceList = $factory->createListFromLoader($choiceLoader);
   ```

 * Passing a query builder closure to `ORMQueryBuilderLoader` was deprecated and
   will not be supported anymore in Symfony 3.0. You should pass resolved query
   builders only.

   Consequently, the arguments `$manager` and `$class` of `ORMQueryBuilderLoader`
   have been deprecated as well.

   Note that the "query_builder" option of `DoctrineType` *does* support
   closures, but the closure is now resolved in the type instead of in the
   loader.

   Before:

   ```php
   use Symfony\Bridge\Doctrine\Form\ChoiceList\ORMQueryBuilderLoader;

   $queryBuilder = function () {
       // return QueryBuilder
   };
   $loader = new ORMQueryBuilderLoader($queryBuilder);
   ```

   After:

   ```php
   use Symfony\Bridge\Doctrine\Form\ChoiceList\ORMQueryBuilderLoader;

   // create $queryBuilder
   $loader = new ORMQueryBuilderLoader($queryBuilder);
   ```

 * The classes `ChoiceToBooleanArrayTransformer`,
   `ChoicesToBooleanArrayTransformer`, `FixRadioInputListener` and
   `FixCheckboxInputListener` were deprecated and will be removed in Symfony 3.0.
   Their functionality is covered by the new classes `RadioListMapper` and
   `CheckboxListMapper`.

 * The ability to translate Doctrine type entries by the translator component
   is now disabled by default and to enable it you must explicitly set the option
   "choice_translation_domain" to true

   Before:

   ```php
   $form->add('products', 'entity', array(
       'class' => 'AppBundle/Entity/Product',
   ));
   ```

   After:

   ```php
   $form->add('products', 'entity', array(
       'class' => 'AppBundle/Entity/Product',
       'choice_translation_domain' => true,
   ));
   ```

 * In the block `choice_widget_options` the `translation_domain` has been replaced
   with the `choice_translation_domain` option.

   Before:

   ```jinja
   {{ choice.label|trans({}, translation_domain) }}
   ```

   After:

   ```jinja
   {{ choice_translation_domain is same as(false) ? choice.label : choice.label|trans({}, choice_translation_domain) }}
   ```

Serializer
----------

 * The `setCamelizedAttributes()` method of the
   `Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer` and
   `Symfony\Component\Serializer\Normalizer\PropertyNormalizer` classes is marked
   as deprecated in favor of the new NameConverter system.

   Before:

   ```php
   $normalizer->setCamelizedAttributes(array('foo_bar', 'bar_foo'));
   ```

   After:

   ```php
   use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
   use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;

   $nameConverter = new CamelCaseToSnakeCaseNameConverter(array('fooBar', 'barFoo'));
   $normalizer = new GetSetMethodNormalizer(null, $nameConverter);
   ```

 * `Symfony\Component\Serializer\Exception\ExceptionInterface` is the new name for the now
   deprecated `Symfony\Component\Serializer\Exception\Exception` interface.

PropertyAccess
--------------

 * `UnexpectedTypeException` now expects three constructor arguments: The invalid property value,
   the `PropertyPathInterface` object and the current index of the property path.

   Before:

   ```php
        use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;

        new UnexpectedTypeException($value, $expectedType);
   ```

   After:

   ```php
        use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;

        new UnexpectedTypeException($value, $path, $pathIndex);
   ```

Config
------

 * The `__toString()` method of the `\Symfony\Component\Config\ConfigCache` is marked as 
   deprecated in favor of the new `getPath()` method.
   
Validator
---------

 * The PHP7-incompatible constraints (Null, True, False) and related validators
   (NullValidator, TrueValidator, FalseValidator) are marked as deprecated
   in favor of their `Is`-prefixed equivalent.

Console
-------

 * The `Symfony\Component\Console\Input\InputDefinition::getSynopsis()` method
   now has an optional argument (it previously had no arguments). If you override
   this method, you'll need to add this argument so that your signature matches:

   Before:

   ```php
   public function getSynopsis()
   {
       // ...
   }
   ```

   After:

   ```php
   public function getSynopsis($short = false)
   {
       // ...
   }
   ```

TwigBundle
----------

 * The `Symfony\Bundle\TwigBundle\TwigDefaultEscapingStrategy` is deprecated and no longer
   used in favor of `Twig_FileExtensionEscapingStrategy`. This means that CSS files automatically
   use the CSS escape strategy. This can cause different behaviour when outputting reserved
   characters.

   Before:

   ```css
   {# styles.css.twig #}

   {# with brand_color: '#123456' #}
   body {
       background: {{ brand_color }};
   }
   ```

   After:

   ```css
   {# styles.css.twig #}

   {# with brand_color: '#123456' #}
   body {
       background: {{ brand_color|raw }};
   }
   ```

FrameworkBundle
---------------

 * The `templating.helper.assets` service was refactored and now returns an object of type
   `Symfony\Bundle\FrameworkBundle\Templating\Helper\AssetsHelper` instead of
   `Symfony\Component\Templating\Helper\CoreAssetsHelper`. You can update your class definition
   or use the `assets.packages` service instead. Using the `assets.packages` service is the recommended 
   way.

   Before:

   ```php
   use Symfony\Component\Templating\Helper\CoreAssetsHelper;

   class DemoService
   {
       private $assetsHelper;

       public function __construct(CoreAssetsHelper $assetsHelper)
       {
           $this->assetsHelper = $assetsHelper;
       }

       public function testMethod()
       {
           return $this->assetsHelper->getUrl('thumbnail.png', null, $this->assetsHelper->getVersion());
       }
   }
   ```

   After:

   ```php
   use Symfony\Component\Asset\Packages;

   class DemoService
   {
       private $assetPackages;

       public function __construct(Packages $assetPackages)
       {
           $this->assetPackages = $assetPackages;
       }

       public function testMethod()
       {
           return $this->assetPackages->getUrl('thumbnail.png').$this->assetPackages->getVersion();
       }
   }
   ```

Security
---------------

 * Injection of the `security.context` service has been reduced to a bare minimum. This means
   that arguments that once hinted `SecurityContext` or `SecurityContextInterface` will have
   to be updated accordingly to either the `TokenStorageInterface` or `AuthorizationCheckerInterface`.
   The following classes now require the `security.token_storage` service instead of the `security.context`,
   please update your extending implementations accordingly.

    * `AbstractAuthenticationListener`
    * `AnonymousAuthenticationListener`
    * `ContextListener`
    * `SimplePreAuthenticationListener`
    * `X509AuthenticationListener`
    * `RemoteUserAuthenticationListener`
    * `BasicAuthenticationListener`
    * `DigestAuthenticationListener`
    * `ExceptionListener`
    * `SwitchUserListener`
    * `AccessListener`
    * `RememberMeListener`

UPGRADE FROM 2.7.1 to 2.7.2
===========================

Form
----

 * In order to fix a few regressions in the new `ChoiceList` implementation,
   a few details had to be changed compared to 2.7.
   
   The legacy `Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface` 
   now does not extend the new `Symfony\Component\Form\ChoiceList\ChoiceListInterface`
   anymore. If you pass an implementation of the old interface in a context
   where the new interface is required, wrap the list into a
   `LegacyChoiceListAdapter`:
   
   Before:
   
   ```php
   use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
   
   function doSomething(ChoiceListInterface $choiceList)
   {
       // ...
   }
   
   doSomething($legacyList);
   ```
   
   After:
   
   ```php
   use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
   use Symfony\Component\Form\ChoiceList\LegacyChoiceListAdapter;
   
   function doSomething(ChoiceListInterface $choiceList)
   {
       // ...
   }
   
   doSomething(new LegacyChoiceListAdapter($legacyList));
   ```
   
   The new `ChoiceListInterface` now has two additional methods
   `getStructuredValues()` and `getOriginalKeys()`. You should add these methods
   if you implement this interface. See their doc blocks and the implementation
   of the core choice lists for inspiration.
   
   The method `ArrayKeyChoiceList::toArrayKey()` was marked as internal. This
   method was never supposed to be used outside the class.
   
   The method `ChoiceListFactoryInterface::createView()` does not accept arrays
   and `Traversable` instances anymore for the `$groupBy` parameter. Pass a
   callable instead.
