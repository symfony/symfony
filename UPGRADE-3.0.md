UPGRADE FROM 2.x to 3.0
=======================

### ClassLoader

 * The `UniversalClassLoader` class has been removed in favor of
   `ClassLoader`. The only difference is that some method names are different:

   | Old name | New name
   | -------- | ---
   | `registerNamespaces()` | `addPrefixes()`
   | `registerPrefixes()` | `addPrefixes()`
   | `registerNamespace()` | `addPrefix()`
   | `registerPrefix()` | `addPrefix()`
   | `getNamespaces()` | `getPrefixes()`
   | `getNamespaceFallbacks()` | `getFallbackDirs()`
   | `getPrefixFallbacks()` | `getFallbackDirs()`

 * The `DebugUniversalClassLoader` class has been removed in favor of
   `DebugClassLoader`. The difference is that the constructor now takes a
   loader to wrap.

### Config

 * `\Symfony\Component\Config\Resource\ResourceInterface::isFresh()` has been removed. Also,
   cache validation through this method (which was still supported in 2.8 for BC) does no longer
   work because the `\Symfony\Component\Config\Resource\BCResourceInterfaceChecker` helper class
   has been removed as well.

 * The `__toString()` method of the `\Symfony\Component\Config\ConfigCache` class
   was removed in favor of the new `getPath()` method.

### Console

 * The `dialog` helper has been removed in favor of the `question` helper.

 * The methods `isQuiet`, `isVerbose`, `isVeryVerbose` and `isDebug` were added
   to `Symfony\Component\Console\Output\OutputInterface`.

 * `ProgressHelper` has been removed in favor of `ProgressBar`.

   Before:

   ```php
   $h = new ProgressHelper();
   $h->start($output, 10);
   for ($i = 1; $i < 5; $i++) {
       usleep(200000);
       $h->advance();
   }
   $h->finish();
   ```

   After:

   ```php
   $bar = new ProgressBar($output, 10);
   $bar->start();
   for ($i = 1; $i < 5; $i++) {
       usleep(200000);
       $bar->advance();
   }
   ```

 * `TableHelper` has been removed in favor of `Table`.

   Before:

   ```php
   $table = $app->getHelperSet()->get('table');
   $table
       ->setHeaders(array('ISBN', 'Title', 'Author'))
       ->setRows(array(
           array('99921-58-10-7', 'Divine Comedy', 'Dante Alighieri'),
           array('9971-5-0210-0', 'A Tale of Two Cities', 'Charles Dickens'),
           array('960-425-059-0', 'The Lord of the Rings', 'J. R. R. Tolkien'),
           array('80-902734-1-6', 'And Then There Were None', 'Agatha Christie'),
       ))
   ;
   $table->render($output);
   ```

   After:

   ```php
   use Symfony\Component\Console\Helper\Table;

   $table = new Table($output);
   $table
       ->setHeaders(array('ISBN', 'Title', 'Author'))
       ->setRows(array(
           array('99921-58-10-7', 'Divine Comedy', 'Dante Alighieri'),
           array('9971-5-0210-0', 'A Tale of Two Cities', 'Charles Dickens'),
           array('960-425-059-0', 'The Lord of the Rings', 'J. R. R. Tolkien'),
           array('80-902734-1-6', 'And Then There Were None', 'Agatha Christie'),
       ))
   ;
   $table->render();
   ```

### DependencyInjection

 * The concept of scopes was removed, the removed methods are:

    - `Symfony\Component\DependencyInjection\ContainerBuilder::getScopes()`
    - `Symfony\Component\DependencyInjection\ContainerBuilder::getScopeChildren()`
    - `Symfony\Component\DependencyInjection\ContainerInterface::enterScope()`
    - `Symfony\Component\DependencyInjection\ContainerInterface::leaveScope()`
    - `Symfony\Component\DependencyInjection\ContainerInterface::addScope()`
    - `Symfony\Component\DependencyInjection\ContainerInterface::hasScope()`
    - `Symfony\Component\DependencyInjection\ContainerInterface::isScopeActive()`
    - `Symfony\Component\DependencyInjection\Definition::setScope()`
    - `Symfony\Component\DependencyInjection\Definition::getScope()`
    - `Symfony\Component\DependencyInjection\Reference::isStrict()`

  Also, the `$scope` and `$strict` parameters of `Symfony\Component\DependencyInjection\ContainerInterface::set()`
  and `Symfony\Component\DependencyInjection\Reference` respectively were removed.

 * A new `shared` flag has been added to the service definition
   in replacement of the `prototype` scope.

   Before:

   ```php
   use Symfony\Component\DependencyInjection\ContainerBuilder;

   $container = new ContainerBuilder();
   $container
       ->register('foo', 'stdClass')
       ->setScope(ContainerBuilder::SCOPE_PROTOTYPE)
   ;
   ```

   ```yml
   services:
       foo:
           class: stdClass
           scope: prototype
   ```

   ```xml
   <services>
       <service id="foo" class="stdClass" scope="prototype" />
   </services>
   ```

   After:

   ```php
   use Symfony\Component\DependencyInjection\ContainerBuilder;

   $container = new ContainerBuilder();
   $container
       ->register('foo', 'stdClass')
       ->setShared(false)
   ;
   ```

   ```yml
   services:
       foo:
           class: stdClass
           shared: false
   ```

   ```xml
   <services>
       <service id="foo" class="stdClass" shared="false" />
   </services>
   ```

 * `Symfony\Component\DependencyInjection\ContainerAware` was removed, use
   `Symfony\Component\DependencyInjection\ContainerAwareTrait` or implement
   `Symfony\Component\DependencyInjection\ContainerAwareInterface` manually

 * The methods `Definition::setFactoryClass()`,
   `Definition::setFactoryMethod()`, and `Definition::setFactoryService()` have
   been removed in favor of `Definition::setFactory()`. Services defined using
   YAML or XML use the same syntax as configurators.

 * Synchronized services are deprecated and the following methods have been
   removed: `ContainerBuilder::synchronize()`, `Definition::isSynchronized()`,
   and `Definition::setSynchronized()`.

### DoctrineBridge

 * The `property` option of `DoctrineType` was removed in favor of the `choice_label` option.

 * The `loader` option of `DoctrineType` was removed. You now have to override the `getLoader()`
   method in your custom type.

 * The `Symfony\Bridge\Doctrine\Form\ChoiceList\EntityChoiceList` was removed in favor
   of `Symfony\Bridge\Doctrine\Form\ChoiceList\DoctrineChoiceLoader`.

 * Passing a query builder closure to `ORMQueryBuilderLoader` is not supported anymore.
   You should pass resolved query builders only.

   Consequently, the arguments `$manager` and `$class` of `ORMQueryBuilderLoader`
   have been removed as well.

   Note that the `query_builder` option of `DoctrineType` *does* support
   closures, but the closure is now resolved in the type instead of in the
   loader.

 * Using the entity provider with a Doctrine repository implementing `UserProviderInterface` is not supported anymore.
   You should make the repository implement `UserLoaderInterface` instead.

### EventDispatcher

 * The interface `Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcherInterface`
   extends `Symfony\Component\EventDispatcher\EventDispatcherInterface`.

### Form

 * The option `options` of the `CollectionType` has been removed in favor
   of the `entry_options` option.

 * The `cascade_validation` option was removed. Use the `constraints` option
   together with the `Valid` constraint instead.

 * Type names were removed. Instead of referencing types by name, you must
   reference them by their fully-qualified class name (FQCN) instead:

   Before:

   ```php
   $form = $this->createFormBuilder()
       ->add('name', 'text')
       ->add('age', 'integer')
       ->getForm();
   ```

   After:

   ```php
   use Symfony\Component\Form\Extension\Core\Type\IntegerType;
   use Symfony\Component\Form\Extension\Core\Type\TextType;

   $form = $this->createFormBuilder()
       ->add('name', TextType::class)
       ->add('age', IntegerType::class)
       ->getForm();
   ```

   If you want to customize the block prefix of a type in Twig, you must now
   implement `FormTypeInterface::getBlockPrefix()`:

   Before:

   ```php
   class UserProfileType extends AbstractType
   {
       public function getName()
       {
           return 'profile';
       }
   }
   ```

   After:

   ```php
   class UserProfileType extends AbstractType
   {
       public function getBlockPrefix()
       {
           return 'profile';
       }
   }
   ```

   If you don't customize `getBlockPrefix()`, it defaults to the class name
   without "Type" suffix in underscore notation (here: "user_profile").

   Type extension must return the fully-qualified class name of the extended
   type from `FormTypeExtensionInterface::getExtendedType()` now.

   Before:

   ```php
   class MyTypeExtension extends AbstractTypeExtension
   {
       public function getExtendedType()
       {
           return 'form';
       }
   }
   ```

   After:

   ```php
   use Symfony\Component\Form\Extension\Core\Type\FormType;

   class MyTypeExtension extends AbstractTypeExtension
   {
       public function getExtendedType()
       {
           return FormType::class;
       }
   }
   ```

 * The `FormTypeInterface::getName()` method was removed.

 * Returning type instances from `FormTypeInterface::getParent()` is not
   supported anymore. Return the fully-qualified class name of the parent
   type class instead.

   Before:

   ```php
   class MyType
   {
       public function getParent()
       {
           return new ParentType();
       }
   }
   ```

   After:

   ```php
   class MyType
   {
       public function getParent()
       {
           return ParentType::class;
       }
   }
   ```

 * The option `type` of the `CollectionType` has been removed in favor of
   the `entry_type` option. The value for the `entry_type` option must be
   the fully-qualified class name (FQCN).

 * Passing type instances to `Form::add()`, `FormBuilder::add()` and the
   `FormFactory::create*()` methods is not supported anymore. Pass the
   fully-qualified class name of the type instead.

   Before:

   ```php
   $form = $this->createForm(new MyType());
   ```

   After:

   ```php
   $form = $this->createForm(MyType::class);
   ```

 * Passing custom data to forms now needs to be done 
   through the options resolver. 

    In the controller:

    Before:
    ```php
    $form = $this->createForm(new MyType($variable), $entity, array(
        'action' => $this->generateUrl('action_route'),
        'method' => 'PUT',
    ));
    ```
    After: 
    ```php
    $form = $this->createForm(MyType::class, $entity, array(
        'action' => $this->generateUrl('action_route'),
        'method' => 'PUT',
        'custom_value' => $variable,
    ));
    ```
    In the form type:
    
    Before:
    ```php
    class MyType extends AbstractType
    {
        private $value;
    
        public function __construct($variableValue)
        {
            $this->value = $value;
        }
        // ...
    }
    ```
    
    After:
    ```php
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $value = $options['custom_value'];
        // ...
    }
    
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'custom_value' => null,
        ));
    }
    ```
 
 * The alias option of the `form.type_extension` tag was removed in favor of
   the `extended_type`/`extended-type` option.

   Before:
   ```xml
   <service id="app.type_extension" class="Vendor\Form\Extension\MyTypeExtension">
       <tag name="form.type_extension" alias="text" />
   </service>
   ```

   After:
   ```xml
   <service id="app.type_extension" class="Vendor\Form\Extension\MyTypeExtension">
       <tag name="form.type_extension" extended-type="Symfony\Component\Form\Extension\Core\Type\TextType" />
   </service>
   ```

 * The `max_length` option was removed. Use the `attr` option instead by setting it to
   an `array` with a `maxlength` key.

 * The `ChoiceToBooleanArrayTransformer`, `ChoicesToBooleanArrayTransformer`,
   `FixRadioInputListener`, and `FixCheckboxInputListener` classes were removed.

 * The `choice_list` option of `ChoiceType` was removed.

 * The option "precision" was renamed to "scale".

   Before:

   ```php
   use Symfony\Component\Form\Extension\Core\Type\NumberType;

   $builder->add('length', NumberType::class, array(
      'precision' => 3,
   ));
   ```

   After:

   ```php
   use Symfony\Component\Form\Extension\Core\Type\NumberType;

   $builder->add('length', NumberType::class, array(
      'scale' => 3,
   ));
   ```

 * The option "`virtual`" was renamed to "`inherit_data`".

   Before:

   ```php
   use Symfony\Component\Form\Extension\Core\Type\FormType;

   $builder->add('address', FormType::class, array(
       'virtual' => true,
   ));
   ```

   After:

   ```php
   use Symfony\Component\Form\Extension\Core\Type\FormType;

   $builder->add('address', FormType::class, array(
       'inherit_data' => true,
   ));
   ```

 * The method `AbstractType::setDefaultOptions(OptionsResolverInterface $resolver)` and
   `AbstractTypeExtension::setDefaultOptions(OptionsResolverInterface $resolver)` have been
   renamed. You should use `AbstractType::configureOptions(OptionsResolver $resolver)` and
   `AbstractTypeExtension::configureOptions(OptionsResolver $resolver)` instead.

 * The methods `Form::bind()` and `Form::isBound()` were removed. You should
   use `Form::submit()` and `Form::isSubmitted()` instead.

   Before:

   ```php
   $form->bind(array(...));
   ```

   After:

   ```php
   $form->submit(array(...));
   ```

 * Passing a `Symfony\Component\HttpFoundation\Request` instance, as was
   supported by `FormInterface::bind()`, is not possible with
   `FormInterface::submit()` anymore. You should use `FormInterface::handleRequest()`
   instead.

   Before:

   ```php
   if ('POST' === $request->getMethod()) {
       $form->bind($request);

       if ($form->isValid()) {
           // ...
       }
   }
   ```

   After:

   ```php
   $form->handleRequest($request);

   if ($form->isValid()) {
       // ...
   }
   ```

   If you want to test whether the form was submitted separately, you can use
   the method `isSubmitted()`:

   ```php
   $form->handleRequest($request);

   if ($form->isSubmitted()) {
      // ...

      if ($form->isValid()) {
          // ...
      }
   }
   ```

 * The events `PRE_BIND`, `BIND` and `POST_BIND` were renamed to `PRE_SUBMIT`, `SUBMIT`
   and `POST_SUBMIT`.

   Before:

   ```php
   $builder->addEventListener(FormEvents::PRE_BIND, function (FormEvent $event) {
       // ...
   });
   ```

   After:

   ```php
   $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
       // ...
   });
   ```

 * The class `VirtualFormAwareIterator` was renamed to `InheritDataAwareIterator`.

   Before:

   ```php
   use Symfony\Component\Form\Util\VirtualFormAwareIterator;

   $iterator = new VirtualFormAwareIterator($forms);
   ```

   After:

   ```php
   use Symfony\Component\Form\Util\InheritDataAwareIterator;

   $iterator = new InheritDataAwareIterator($forms);
   ```

 * The `TypeTestCase` class was moved from the `Symfony\Component\Form\Tests\Extension\Core\Type` namespace to the `Symfony\Component\Form\Test` namespace.

   Before:

   ```php
   use Symfony\Component\Form\Tests\Extension\Core\Type\TypeTestCase

   class MyTypeTest extends TypeTestCase
   {
       // ...
   }
   ```

   After:

   ```php
   use Symfony\Component\Form\Test\TypeTestCase;

   class MyTypeTest extends TypeTestCase
   {
       // ...
   }
   ```

 * The option "options" of the CollectionType has been renamed to "entry_options".

 * The option "type" of the CollectionType has been renamed to "entry_type".
   As a value for the option you must provide the fully-qualified class name (FQCN)
   now as well.

 * The `FormIntegrationTestCase` and `FormPerformanceTestCase` classes were moved form the `Symfony\Component\Form\Tests` namespace to the `Symfony\Component\Form\Test` namespace.

 * The constants `ROUND_HALFEVEN`, `ROUND_HALFUP` and `ROUND_HALFDOWN` in class
   `NumberToLocalizedStringTransformer` were renamed to `ROUND_HALF_EVEN`,
   `ROUND_HALF_UP` and `ROUND_HALF_DOWN`.

 * The `Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface` was
   removed in favor of `Symfony\Component\Form\ChoiceList\ChoiceListInterface`.

 * `Symfony\Component\Form\Extension\Core\View\ChoiceView` was removed in favor of
   `Symfony\Component\Form\ChoiceList\View\ChoiceView`.

 * The interface `Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface`
   and all of its implementations were removed. Use the new interface
   `Symfony\Component\Security\Csrf\CsrfTokenManagerInterface` instead.

 * The options "`csrf_provider`" and "`intention`" were renamed to  "`csrf_token_generator`"
   and "`csrf_token_id`".

 * The method `Form::getErrorsAsString()` was removed. Use `Form::getErrors()`
   instead with the argument `$deep` set to true and `$flatten` set to false
   and cast the returned iterator to a string (if not done implicitly by PHP).

   Before:

   ```php
   echo $form->getErrorsAsString();
   ```

   After:

   ```php
   echo $form->getErrors(true, false);
   ```

 * The `Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceList` class has been removed in
   favor of `Symfony\Component\Form\ChoiceList\ArrayChoiceList`.

 * The `Symfony\Component\Form\Extension\Core\ChoiceList\LazyChoiceList` class has been removed in
   favor of `Symfony\Component\Form\ChoiceList\LazyChoiceList`.

 * The `Symfony\Component\Form\Extension\Core\ChoiceList\ObjectChoiceList` class has been removed in
   favor of `Symfony\Component\Form\ChoiceList\ArrayChoiceList`.

 * The `Symfony\Component\Form\Extension\Core\ChoiceList\SimpleChoiceList` class has been removed in
   favor of `Symfony\Component\Form\ChoiceList\ArrayChoiceList`.

 * The `TimezoneType::getTimezones()` method was removed. You should not use
   this method.

 * The `Symfony\Component\Form\ChoiceList\ArrayKeyChoiceList` class has been removed in
   favor of `Symfony\Component\Form\ChoiceList\ArrayChoiceList`.

### FrameworkBundle

 * The `config:debug`, `container:debug`, `router:debug`, `translation:debug`
   and `yaml:lint` commands have been deprecated since Symfony 2.7 and will
   be removed in Symfony 3.0. Use the `debug:config`, `debug:container`,
   `debug:router`, `debug:translation` and `lint:yaml` commands instead.

 * The `getRequest` method of the base `Controller` class has been deprecated
   since Symfony 2.4 and must be therefore removed in 3.0. The only reliable
   way to get the `Request` object is to inject it in the action method.

   Before:

   ```php
   namespace Acme\FooBundle\Controller;

   class DemoController
   {
       public function showAction()
       {
           $request = $this->getRequest();
           // ...
       }
   }
   ```

   After:

   ```php
   namespace Acme\FooBundle\Controller;

   use Symfony\Component\HttpFoundation\Request;

   class DemoController
   {
       public function showAction(Request $request)
       {
           // ...
       }
   }
   ```

 * In Symfony 2.7 a small BC break was introduced with the new choices_as_values
   option. In order to have the choice values populated to the html value attribute
   you had to define the choice_value option. This is now not any more needed.

   Before:

   ```php
   $form->add('status', 'choice', array(
       'choices' => array(
           'Enabled' => Status::ENABLED,
           'Disabled' => Status::DISABLED,
           'Ignored' => Status::IGNORED,
       ),
       // choices_as_values defaults to true in Symfony 3.0
       // and setting it to anything else is deprecated as of 3.0
       'choices_as_values' => true,
       // important if you rely on your option value attribute (e.g. for JavaScript)
       // this will keep the same functionality as before
       'choice_value' => function ($choice) {
           return $choice;
       },
   ));
   ```

   After:

   ```php
   $form->add('status', ChoiceType::class, array(
       'choices' => array(
           'Enabled' => Status::ENABLED,
           'Disabled' => Status::DISABLED,
           'Ignored' => Status::IGNORED,
       )
   ));
   ```

 * The `request` service was removed. You must inject the `request_stack`
   service instead.

 * The `enctype` method of the `form` helper was removed. You should use the
   new method `start` instead.

   Before:

   ```php
   <form method="post" action="http://example.com" <?php echo $view['form']->enctype($form) ?>>
       ...
   </form>
   ```

   After:

   ```php
   <?php echo $view['form']->start($form) ?>
       ...
   <?php echo $view['form']->end($form) ?>
   ```

   The method and action of the form default to "POST" and the current
   document. If you want to change these values, you can set them explicitly in
   the controller.

   Alternative 1:

   ```php
   $form = $this->createForm('my_form', $formData, array(
       'method' => 'PUT',
       'action' => $this->generateUrl('target_route'),
   ));
   ```

   Alternative 2:

   ```php
   $form = $this->createFormBuilder($formData)
       // ...
       ->setMethod('PUT')
       ->setAction($this->generateUrl('target_route'))
       ->getForm();
   ```

   It is also possible to override the method and the action in the template:

   ```php
   <?php echo $view['form']->start($form, array('method' => 'GET', 'action' => 'http://example.com')) ?>
       ...
   <?php echo $view['form']->end($form) ?>
   ```

 * The `RouterApacheDumperCommand` was removed.

 * The `templating.helper.router` service was moved to `templating_php.xml`. You
   have to ensure that the PHP templating engine is enabled to be able to use it:

   ```yaml
   framework:
       templating:
           engines: ['php']
   ```

 * The `form.csrf_provider` service is removed as it implements an adapter for
   the new token manager to the deprecated
   `Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface`
   interface.
   The `security.csrf.token_manager` should be used instead.

 * The `validator.mapping.cache.apc` service has been removed in favor of the `validator.mapping.cache.doctrine.apc` one.

 * The ability to pass `apc` as the `framework.validation.cache` configuration key value has been removed.
   Use `validator.mapping.cache.doctrine.apc` instead:

   Before:

   ```yaml
   framework:
       validation:
           cache: apc
   ```

   After:

   ```yaml
   framework:
       validation:
           cache: validator.mapping.cache.doctrine.apc
   ```

### HttpKernel

 * The `Symfony\Component\HttpKernel\Log\LoggerInterface` has been removed in
   favor of `Psr\Log\LoggerInterface`. The only difference is that some method
   names are different:

   | Old name | New name
   | -------- | ---
   | `emerg()` | `emergency()`
   | `crit()` | `critical()`
   | `err()` | `error()`
   | `warn()` | `warning()`

   The previous method renames also happened to the following classes:

     * `Symfony\Bridge\Monolog\Logger`
     * `Symfony\Component\HttpKernel\Log\NullLogger`

 * The `Symfony\Component\HttpKernel\Kernel::init()` method has been removed.

 * The following classes have been renamed as they have been moved to the
   Debug component:

   | Old name | New name
   | -------- | ---
   | `Symfony\Component\HttpKernel\Debug\ErrorHandler` | `Symfony\Component\Debug\ErrorHandler`
   | `Symfony\Component\HttpKernel\Debug\ExceptionHandler` | `Symfony\Component\Debug\ExceptionHandler`
   | `Symfony\Component\HttpKernel\Exception\FatalErrorException` | `Symfony\Component\Debug\Exception\FatalErrorException`
   | `Symfony\Component\HttpKernel\Exception\FlattenException` | `Symfony\Component\Debug\Exception\FlattenException`

 * The `Symfony\Component\HttpKernel\EventListener\ExceptionListener` now
   passes the Request format as the `_format` argument instead of `format`.

 * The `Symfony\Component\HttpKernel\DependencyInjection\RegisterListenersPass` has been renamed to
   `Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass` and moved to the EventDispatcher component.

### Locale

 * The Locale component was removed and replaced by the Intl component.
   Instead of the methods in `Symfony\Component\Locale\Locale`, you should use
   these equivalent methods in `Symfony\Component\Intl\Intl` now:

   | Old way | New way
   | ------- | ---
   | `Locale::getDisplayCountries()` | `Intl::getRegionBundle()->getCountryNames()`
   | `Locale::getCountries()` | `array_keys(Intl::getRegionBundle()->getCountryNames())`
   | `Locale::getDisplayLanguages()` | `Intl::getLanguageBundle()->getLanguageNames()`
   | `Locale::getLanguages()` | `array_keys(Intl::getLanguageBundle()->getLanguageNames())`
   | `Locale::getDisplayLocales()` | `Intl::getLocaleBundle()->getLocaleNames()`
   | `Locale::getLocales()` | `array_keys(Intl::getLocaleBundle()->getLocaleNames())`

### PropertyAccess

 * Renamed `PropertyAccess::getPropertyAccessor` to `createPropertyAccessor`.

   Before:

   ```php
   use Symfony\Component\PropertyAccess\PropertyAccess;

   $accessor = PropertyAccess::getPropertyAccessor();
   ```

   After:

   ```php
   use Symfony\Component\PropertyAccess\PropertyAccess;

   $accessor = PropertyAccess::createPropertyAccessor();
   ```

### Routing

 * Some route settings have been renamed:

     * The `pattern` setting has been removed in favor of `path`
     * The `_scheme` and `_method` requirements have been moved to the `schemes` and `methods` settings

   Before:

   ```yaml
   article_edit:
       pattern: /article/{id}
       requirements: { '_method': 'POST|PUT', '_scheme': 'https', 'id': '\d+' }
   ```

   ```xml
   <route id="article_edit" pattern="/article/{id}">
       <requirement key="_method">POST|PUT</requirement>
       <requirement key="_scheme">https</requirement>
       <requirement key="id">\d+</requirement>
   </route>
   ```

   ```php
   $route = new Route();
   $route->setPattern('/article/{id}');
   $route->setRequirement('_method', 'POST|PUT');
   $route->setRequirement('_scheme', 'https');
   ```

   After:

   ```yaml
   article_edit:
       path: /article/{id}
       methods: [POST, PUT]
       schemes: https
       requirements: { 'id': '\d+' }
   ```

   ```xml
   <route id="article_edit" path="/article/{id}" methods="POST PUT" schemes="https">
       <requirement key="id">\d+</requirement>
   </route>
   ```

   ```php
   $route = new Route();
   $route->setPath('/article/{id}');
   $route->setMethods(array('POST', 'PUT'));
   $route->setSchemes('https');
   ```

 * The `ApacheMatcherDumper` and `ApacheUrlMatcher` were removed since
   the performance gains were minimal and it's hard to replicate the behaviour
   of PHP implementation.

 * The `getMatcherDumperInstance()` and `getGeneratorDumperInstance()` methods in the
   `Symfony\Component\Routing\Router` have been changed from `public` to `protected`.

 * Use the constants defined in the UrlGeneratorInterface for the $referenceType argument of the UrlGeneratorInterface::generate method.

   Before:

   ```php
   // url generated in controller
   $this->generateUrl('blog_show', array('slug' => 'my-blog-post'), true);

   // url generated in @router service
   $router->generate('blog_show', array('slug' => 'my-blog-post'), true);
   ```

   After:

   ```php
   use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

   // url generated in controller
   $this->generateUrl('blog_show', array('slug' => 'my-blog-post'), UrlGeneratorInterface::ABSOLUTE_URL);

   // url generated in @router service
   $router->generate('blog_show', array('slug' => 'my-blog-post'), UrlGeneratorInterface::ABSOLUTE_URL);
   ```

### Security

 * The `Resources/` directory was moved to `Core/Resources/`

 * The `key` settings of `anonymous`, `remember_me` and `http_digest` are
   renamed to `secret`.

   Before:

   ```yaml
   security:
       # ...
       firewalls:
           default:
               # ...
               anonymous: { key: "%secret%" }
               remember_me:
                   key: "%secret%"
               http_digest:
                   key: "%secret%"
   ```

   ```xml
   <!-- ... -->
   <config>
       <!-- ... -->

       <firewall>
           <!-- ... -->

           <anonymous key="%secret%"/>
           <remember-me key="%secret%"/>
           <http-digest key="%secret%"/>
       </firewall>
   </config>
   ```

   ```php
   // ...
   $container->loadFromExtension('security', array(
       // ...
       'firewalls' => array(
           // ...
           'anonymous' => array('key' => '%secret%'),
           'remember_me' => array('key' => '%secret%'),
           'http_digest' => array('key' => '%secret%'),
       ),
   ));
   ```

   After:

   ```yaml
   security:
       # ...
       firewalls:
           default:
               # ...
               anonymous: { secret: "%secret%" }
               remember_me:
                   secret: "%secret%"
               http_digest:
                   secret: "%secret%"
   ```

   ```xml
   <!-- ... -->
   <config>
       <!-- ... -->

       <firewall>
           <!-- ... -->

           <anonymous secret="%secret%"/>
           <remember-me secret="%secret%"/>
           <http-digest secret="%secret%"/>
       </firewall>
   </config>
   ```

   ```php
   // ...
   $container->loadFromExtension('security', array(
       // ...
       'firewalls' => array(
           // ...
           'anonymous' => array('secret' => '%secret%'),
           'remember_me' => array('secret' => '%secret%'),
           'http_digest' => array('secret' => '%secret%'),
       ),
   ));
  ```

 * The `AbstractVoter` class was removed. Instead, extend the new `Voter` class,
   introduced in 2.8, and move your voting logic to the to the `supports($attribute, $subject)`
   and `voteOnAttribute($attribute, $object, TokenInterface $token)` methods.

 * The `vote()` method from the `VoterInterface` was changed to now accept arbitrary
   types, and not only objects.

 * The `supportsClass` and `supportsAttribute` methods were
   removed from the `VoterInterface` interface.

   Before:

   ```php
   class MyVoter extends AbstractVoter
   {
       protected function getSupportedAttributes()
       {
           return array('CREATE', 'EDIT');
       }

       protected function getSupportedClasses()
       {
           return array('AppBundle\Entity\Post');
       }

       // ...
   }
   ```

   After:

   ```php
   use Symfony\Component\Security\Core\Authorization\Voter\Voter;

   class MyVoter extends Voter
   {
       protected function supports($attribute, $object)
       {
           return $object instanceof Post && in_array($attribute, array('CREATE', 'EDIT'));
       }

       protected function voteOnAttribute($attribute, $object, TokenInterface $token)
       {
           // Return true or false
       }
   }
   ```

 * The `AbstractVoter::isGranted()` method has been replaced by `Voter::voteOnAttribute()`.

   Before:

   ```php
   class MyVoter extends AbstractVoter
   {
       protected function isGranted($attribute, $object, $user = null)
       {
           return 'EDIT' === $attribute && $user === $object->getAuthor();
       }

       // ...
   }
   ```

   After:

   ```php
   class MyVoter extends Voter
   {
       protected function voteOnAttribute($attribute, $object, TokenInterface $token)
       {
           return 'EDIT' === $attribute && $token->getUser() === $object->getAuthor();
       }

       // ...
   }
   ```

 * The `supportsAttribute()` and `supportsClass()` methods of the `AuthenticatedVoter`, `ExpressionVoter`,
   and `RoleVoter` classes have been removed.

 * The `intention` option was renamed to `csrf_token_id` for all the authentication listeners.

 * The `csrf_provider` option was renamed to `csrf_token_generator` for all the authentication listeners.

### SecurityBundle

 * The `intention` firewall listener setting was renamed to `csrf_token_id`.

 * The `csrf_provider` firewall listener setting was renamed to `csrf_token_generator`.

### Serializer

 * The `setCamelizedAttributes()` method of the
   `Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer` and
   `Symfony\Component\Serializer\Normalizer\PropertyNormalizer` classes
   was removed.

 * The `Symfony\Component\Serializer\Exception\Exception` interface was removed
   in favor of the new `Symfony\Component\Serializer\Exception\ExceptionInterface`.

### Translator

 * The `Translator::setFallbackLocale()` method has been removed in favor of
   `Translator::setFallbackLocales()`.

 * The `getMessages()` method of the `Symfony\Component\Translation\Translator`
   class was removed. You should use the `getCatalogue()` method of the
   `Symfony\Component\Translation\TranslatorBagInterface`.

### Twig Bridge

 * The `twig:lint` command has been deprecated since Symfony 2.7 and will be
   removed in Symfony 3.0. Use the `lint:twig` command instead.

 * The `render` tag is deprecated in favor of the `render` function.

 * The `form_enctype` helper was removed. You should use the new `form_start`
   function instead.

   Before:

   ```php
   <form method="post" action="http://example.com" {{ form_enctype(form) }}>
       ...
   </form>
   ```

   After:

   ```jinja
   {{ form_start(form) }}
       ...
   {{ form_end(form) }}
   ```

   The method and action of the form default to "POST" and the current
   document. If you want to change these values, you can set them explicitly in
   the controller.

   Alternative 1:

   ```php
   $form = $this->createForm('my_form', $formData, array(
       'method' => 'PUT',
       'action' => $this->generateUrl('target_route'),
   ));
   ```

   Alternative 2:

   ```php
   $form = $this->createFormBuilder($formData)
       // ...
       ->setMethod('PUT')
       ->setAction($this->generateUrl('target_route'))
       ->getForm();
   ```

   It is also possible to override the method and the action in the template:

   ```jinja
   {{ form_start(form, {'method': 'GET', 'action': 'http://example.com'}) }}
       ...
   {{ form_end(form) }}
   ```

### TwigBundle

 * The `Symfony\Bundle\TwigBundle\TwigDefaultEscapingStrategy` was removed
   in favor of `Twig_FileExtensionEscapingStrategy`.

 * The `twig:debug` command has been deprecated since Symfony 2.7 and will be
   removed in Symfony 3.0. Use the `debug:twig` command instead.

### Validator

 * The PHP7-incompatible constraints (`Null`, `True`, `False`) and their related
   validators (`NullValidator`, `TrueValidator`, `FalseValidator`) have been
   removed in favor of their `Is`-prefixed equivalent.

 * The class `Symfony\Component\Validator\Mapping\Cache\ApcCache` has been removed in favor
   of `Symfony\Component\Validator\Mapping\Cache\DoctrineCache`.

   Before:

   ```php
   use Symfony\Component\Validator\Mapping\Cache\ApcCache;

   $cache = new ApcCache('symfony.validator');
   ```

   After:

   ```php
   use Symfony\Component\Validator\Mapping\Cache\DoctrineCache;
   use Doctrine\Common\Cache\ApcCache;

   $apcCache = new ApcCache();
   $apcCache->setNamespace('symfony.validator');

   $cache = new DoctrineCache($apcCache);
   ```

 * The constraints `Optional` and `Required` were moved to the
   `Symfony\Component\Validator\Constraints\` namespace. You should adapt
   the path wherever you used them.

   Before:

   ```php
   use Symfony\Component\Validator\Constraints as Assert;

   /**
    * @Assert\Collection({
    *     "foo" = @Assert\Collection\Required(),
    *     "bar" = @Assert\Collection\Optional(),
    * })
    */
   private $property;
   ```

   After:

   ```php
   use Symfony\Component\Validator\Constraints as Assert;

   /**
    * @Assert\Collection({
    *     "foo" = @Assert\Required(),
    *     "bar" = @Assert\Optional(),
    * })
    */
   private $property;
   ```

 * The option "`methods`" of the `Callback` constraint was removed. You should
   use the option "`callback`" instead. If you have multiple callbacks, add
   multiple callback constraints instead.

   Before (YAML):

   ```yaml
   constraints:
     - Callback: [firstCallback, secondCallback]
   ```

   After (YAML):

   ```yaml
   constraints:
     - Callback: firstCallback
     - Callback: secondCallback
   ```

   When using annotations, you can now put the `Callback` constraint directly on
   the method that should be executed.

   Before (Annotations):

   ```php
   use Symfony\Component\Validator\Constraints as Assert;
   use Symfony\Component\Validator\ExecutionContextInterface;

   /**
    * @Assert\Callback({"callback"})
    */
   class MyClass
   {
       public function callback(ExecutionContextInterface $context)
       {
           // ...
       }
   }
   ```

   After (Annotations):

   ```php
   use Symfony\Component\Validator\Constraints as Assert;
   use Symfony\Component\Validator\ExecutionContextInterface;

   class MyClass
   {
       /**
        * @Assert\Callback
        */
       public function callback(ExecutionContextInterface $context)
       {
           // ...
       }
   }
   ```

 * The interface `ValidatorInterface` was replaced by the more powerful
   interface `Validator\ValidatorInterface`. The signature of the `validate()`
   method is slightly different in that interface and accepts a value, zero
   or more constraints and validation group. It replaces both
   `validate()` and `validateValue()` in the previous interface.

   Before:

   ```php
   $validator->validate($object, 'Strict');

   $validator->validateValue($value, new NotNull());
   ```

   After:

   ```php
   $validator->validate($object, null, 'Strict');

   $validator->validate($value, new NotNull());
   ```

   Apart from this change, the new methods `startContext()` and `inContext()`
   were added. The first of them allows to run multiple validations in the
   same context and aggregate their violations:

   ```php
   $violations = $validator->startContext()
       ->atPath('firstName')->validate($firstName, new NotNull())
       ->atPath('age')->validate($age, new Type('integer'))
       ->getViolations();
   ```

   The second allows to run validation in an existing context. This is
   especially useful when calling the validator from within constraint
   validators:

   ```php
   $validator->inContext($context)->validate($object);
   ```

   Instead of a `Validator`, the validator builder now returns a
   `Validator\RecursiveValidator` instead.

 * The interface `ValidationVisitorInterface` and its implementation
   `ValidationVisitor` were removed. The implementation of the visitor pattern
   was flawed. Fixing that implementation would have drastically slowed down
   the validator execution, so the visitor was removed completely instead.

   Along with the visitor, the method `accept()` was removed from
   `MetadataInterface`.

 * The interface `MetadataInterface` was moved to the `Mapping` namespace.

   Before:

   ```php
   use Symfony\Component\Validator\MetadataInterface;
   ```

   After:

   ```php
   use Symfony\Component\Validator\Mapping\MetadataInterface;
   ```

   The methods `getCascadingStrategy()` and `getTraversalStrategy()` were
   added to the interface. The first method should return a bit mask of the
   constants in class `CascadingStrategy`. The second should return a bit
   mask of the constants in `TraversalStrategy`.

   Example:

   ```php
   use Symfony\Component\Validator\Mapping\TraversalStrategy;

   public function getTraversalStrategy()
   {
       return TraversalStrategy::TRAVERSE;
   }
   ```

 * The interface `PropertyMetadataInterface` was moved to the `Mapping`
   namespace.

   Before:

   ```php
   use Symfony\Component\Validator\PropertyMetadataInterface;
   ```

   After:

   ```php
   use Symfony\Component\Validator\Mapping\PropertyMetadataInterface;
   ```

 * The interface `PropertyMetadataContainerInterface` was moved to the `Mapping`
   namespace and renamed to `ClassMetadataInterface`.

   Before:

   ```php
   use Symfony\Component\Validator\PropertyMetadataContainerInterface;
   ```

   After:

   ```php
   use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
   ```

   The interface now contains four additional methods:

    * `getConstrainedProperties()`
    * `hasGroupSequence()`
    * `getGroupSequence()`
    * `isGroupSequenceProvider()`

   See the inline documentation of these methods for more information.

 * The interface `ClassBasedInterface` was removed. You should use
   `Mapping\ClassMetadataInterface` instead:

   Before:

   ```php
   use Symfony\Component\Validator\ClassBasedInterface;

   class MyClassMetadata implements ClassBasedInterface
   {
       // ...
   }
   ```

   After:

   ```php
   use Symfony\Component\Validator\Mapping\ClassMetadataInterface;

   class MyClassMetadata implements ClassMetadataInterface
   {
       // ...
   }
   ```

 * The class `ElementMetadata` was renamed to `GenericMetadata`.

   Before:

   ```php
   use Symfony\Component\Validator\Mapping\ElementMetadata;

   class MyMetadata extends ElementMetadata
   {
   }
   ```

   After:

   ```php
   use Symfony\Component\Validator\Mapping\GenericMetadata;

   class MyMetadata extends GenericMetadata
   {
   }
   ```

 * The interface `ExecutionContextInterface` and its implementation
   `ExecutionContext` were moved to the `Context` namespace.

   Before:

   ```php
   use Symfony\Component\Validator\ExecutionContextInterface;
   ```

   After:

   ```php
   use Symfony\Component\Validator\Context\ExecutionContextInterface;
   ```

   The interface now contains the following additional methods:

    * `getValidator()`
    * `getObject()`
    * `setNode()`
    * `setGroup()`
    * `markGroupAsValidated()`
    * `isGroupValidated()`
    * `markConstraintAsValidated()`
    * `isConstraintValidated()`

   See the inline documentation of these methods for more information.

   The method `addViolationAt()` was removed. You should use `buildViolation()`
   instead.

   Before:

   ```php
   $context->addViolationAt('property', 'The value {{ value }} is invalid.', array(
       '{{ value }}' => $invalidValue,
   ));
   ```

   After:

   ```php
   $context->buildViolation('The value {{ value }} is invalid.')
       ->atPath('property')
       ->setParameter('{{ value }}', $invalidValue)
       ->addViolation();
   ```

   The methods `validate()` and `validateValue()` were removed. You should use
   `getValidator()` together with `inContext()` instead.

   Before:

   ```php
   $context->validate($object);
   ```

   After:

   ```php
   $context->getValidator()
       ->inContext($context)
       ->validate($object);
   ```

   The parameters `$invalidValue`, `$plural` and `$code` were removed from
   `addViolation()`. You should use `buildViolation()` instead. See above for
   an example.

   The method `getMetadataFactory()` was removed. You can use `getValidator()`
   instead and use the methods `getMetadataFor()` or `hasMetadataFor()` on the
   validator instance.

   Before:

   ```php
   $metadata = $context->getMetadataFactory()->getMetadataFor($myClass);
   ```

   After:

   ```php
   $metadata = $context->getValidator()->getMetadataFor($myClass);
   ```

 * The interface `GlobalExecutionContextInterface` was removed. Most of the
   information provided by that interface can be queried from
   `Context\ExecutionContextInterface` instead.

 * The interface `MetadataFactoryInterface` was moved to the `Mapping\Factory`
   namespace along with its implementations `BlackholeMetadataFactory` and
   `ClassMetadataFactory`. These classes were furthermore renamed to
   `BlackHoleMetadataFactory` and `LazyLoadingMetadataFactory`.

   Before:

   ```php
   use Symfony\Component\Validator\Mapping\ClassMetadataFactory;

   $factory = new ClassMetadataFactory($loader);
   ```

   After:

   ```php
   use Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;

   $factory = new LazyLoadingMetadataFactory($loader);
   ```

 * The option `$deep` was removed from the constraint `Valid`. When traversing
   arrays, nested arrays are always traversed (same behavior as before). When
   traversing nested objects, their traversal strategy is used.

 * The method `ValidatorBuilder::setPropertyAccessor()` was removed. The
   validator now functions without a property accessor.

 * The methods `getMessageParameters()` and `getMessagePluralization()` in
   `ConstraintViolation` were renamed to `getParameters()` and `getPlural()`.

   Before:

   ```php
   $parameters = $violation->getMessageParameters();
   $plural = $violation->getMessagePluralization();
   ```

   After:

   ```php
   $parameters = $violation->getParameters();
   $plural = $violation->getPlural();
   ```

 * The class `Symfony\Component\Validator\DefaultTranslator` was removed. You
   should use `Symfony\Component\Translation\IdentityTranslator` instead.

   Before:

   ```php
   $translator = new \Symfony\Component\Validator\DefaultTranslator();
   ```

   After:

   ```php
   $translator = new \Symfony\Component\Translation\IdentityTranslator();
   $translator->setLocale('en');
   ```

### Yaml

 * Using a colon in an unquoted mapping value leads to a `ParseException`.
 * Starting an unquoted string with `@`, `` ` ``, `|`, or `>` leads to a `ParseException`.
 * When surrounding strings with double-quotes, you must now escape `\` characters. Not
   escaping those characters (when surrounded by double-quotes) leads to a `ParseException`.

   Before:

   ```yml
   class: "Foo\Var"
   ```

   After:

   ```yml
   class: "Foo\\Var"
   ```


 * The ability to pass file names to `Yaml::parse()` has been removed.

   Before:

   ```php
   Yaml::parse($fileName);
   ```

   After:

   ```php
   Yaml::parse(file_get_contents($fileName));
   ```

### WebProfiler

 * The `profiler:import` and `profiler:export` commands have been removed.

 * All the profiler storages different than `FileProfilerStorage` have been
   removed. The removed classes are:

    - `Symfony\Component\HttpKernel\Profiler\BaseMemcacheProfilerStorage`
    - `Symfony\Component\HttpKernel\Profiler\MemcachedProfilerStorage`
    - `Symfony\Component\HttpKernel\Profiler\MemcacheProfilerStorage`
    - `Symfony\Component\HttpKernel\Profiler\MongoDbProfilerStorage`
    - `Symfony\Component\HttpKernel\Profiler\MysqlProfilerStorage`
    - `Symfony\Component\HttpKernel\Profiler\PdoProfilerStorage`
    - `Symfony\Component\HttpKernel\Profiler\RedisProfilerStorage`
    - `Symfony\Component\HttpKernel\Profiler\SqliteProfilerStorage`

### Process

 * `Process::setStdin()` and `Process::getStdin()` have been removed. Use
   `Process::setInput()` and `Process::getInput()` that works the same way.
 * `Process::setInput()` and `ProcessBuilder::setInput()` do not accept non-scalar types.

### HttpFoundation

 * Removed the feature that allowed finding deep items in `ParameterBag::get()`.
   This may affect you when getting parameters from the `Request` class:

   Before:

   ```php
   $request->query->get('foo[bar]', null, true);
   ```

   After:

   ```php
   $request->query->get('foo')['bar'];
   ```
