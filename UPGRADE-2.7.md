UPGRADE FROM 2.6 to 2.7
=======================

HttpKernel
----------

 * The `$cache` argument type of the `dumpContainer()` method in the `Symfony\Component\HttpKernel\Kernel`
   class was changed from `Symfony\Component\Config\ConfigCache` to `Symfony\Component\Config\ConfigCacheInterface`.

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

 * `Symfony\Component\Form\Extension\Core\ChoiceList\View\ChoiceView` was
   deprecated and will be removed in Symfony 3.0. You should use
   `Symfony\Component\Form\ChoiceList\View\ChoiceView` instead.

   Note that the order of the arguments passed to the constructor was inverted.

   Before:

   ```php
   use Symfony\Component\Form\Extension\Core\ChoiceList\View\ChoiceView;

   $view = new ChoiceView($data, 'value', 'Label');
   ```

   After:

   ```php
   use Symfony\Component\Form\ChoiceList\View\ChoiceView;

   $view = new ChoiceView('Label', 'value', $data);
   ```

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
   {{ choice_translation_domain is sameas(false) ? choice.label : choice.label|trans({}, choice_translation_domain) }}
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
