UPGRADE FROM 2.x to 3.0
=======================

### ClassLoader

 * The `UniversalClassLoader` class has been removed in favor of
   `ClassLoader`. The only difference is that some method names are different:

      * `registerNamespaces()` -> `addPrefixes()`
      * `registerPrefixes()` -> `addPrefixes()`
      * `registerNamespaces()` -> `addPrefix()`
      * `registerPrefixes()` -> `addPrefix()`
      * `getNamespaces()` -> `getPrefixes()`
      * `getNamespaceFallbacks()` -> `getFallbackDirs()`
      * `getPrefixFallbacks()` -> `getFallbackDirs()`

 * The `DebugUniversalClassLoader` class has been removed in favor of
   `DebugClassLoader`. The difference is that the constructor now takes a
   loader to wrap.

### Console

 * The `dialog` helper has been removed in favor of the `question` helper.

 * The methods `isQuiet`, `isVerbose`, `isVeryVerbose` and `isDebug` were added
   to `Symfony\Component\Console\Output\OutputInterface`.

 * `ProgressHelper` has been removed in favor of `ProgressBar`.

   Before:

   ```
   $h = new ProgressHelper();
   $h->start($output, 10);
   for ($i = 1; $i < 5; $i++) {
       usleep(200000);
       $h->advance();
   }
   $h->finish();
   ```

   After:

   ```
   $bar = new ProgressBar($output, 10);
   $bar->start();
   for ($i = 1; $i < 5; $i++) {
       usleep(200000);
       $bar->advance();
   }
   ```

 * `TableHelper` has been removed in favor of `Table`.

   Before:

   ```
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

   ```
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

### EventDispatcher

 * The interface `Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcherInterface`
   extends `Symfony\Component\EventDispatcher\EventDispatcherInterface`.

### Form

 * The methods `Form::bind()` and `Form::isBound()` were removed. You should
   use `Form::submit()` and `Form::isSubmitted()` instead.

   Before:

   ```
   $form->bind(array(...));
   ```

   After:

   ```
   $form->submit(array(...));
   ```

 * Passing a `Symfony\Component\HttpFoundation\Request` instance, as was
   supported by `FormInterface::bind()`, is not possible with
   `FormInterface::submit()` anymore. You should use `FormInterface::handleRequest()`
   instead.

   Before:

   ```
   if ('POST' === $request->getMethod()) {
       $form->bind($request);

       if ($form->isValid()) {
           // ...
       }
   }
   ```

   After:

   ```
   $form->handleRequest($request);

   if ($form->isValid()) {
       // ...
   }
   ```

   If you want to test whether the form was submitted separately, you can use
   the method `isSubmitted()`:

   ```
   $form->handleRequest($request);

   if ($form->isSubmitted()) {
      // ...

      if ($form->isValid()) {
          // ...
      }
   }
   ```

 * The events PRE_BIND, BIND and POST_BIND were renamed to PRE_SUBMIT, SUBMIT
   and POST_SUBMIT.

   Before:

   ```
   $builder->addEventListener(FormEvents::PRE_BIND, function (FormEvent $event) {
       // ...
   });
   ```

   After:

   ```
   $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
       // ...
   });
   ```

 * The option "virtual" was renamed to "inherit_data".

   Before:

   ```
   $builder->add('address', 'form', array(
       'virtual' => true,
   ));
   ```

   After:

   ```
   $builder->add('address', 'form', array(
       'inherit_data' => true,
   ));
   ```

 * The class VirtualFormAwareIterator was renamed to InheritDataAwareIterator.

   Before:

   ```
   use Symfony\Component\Form\Util\VirtualFormAwareIterator;

   $iterator = new VirtualFormAwareIterator($forms);
   ```

   After:

   ```
   use Symfony\Component\Form\Util\InheritDataAwareIterator;

   $iterator = new InheritDataAwareIterator($forms);
   ```

 * The `TypeTestCase` class was moved from the `Symfony\Component\Form\Tests\Extension\Core\Type` namespace to the `Symfony\Component\Form\Test` namespace.

   Before:

   ```
   use Symfony\Component\Form\Tests\Extension\Core\Type\TypeTestCase

   class MyTypeTest extends TypeTestCase
   {
       // ...
   }
   ```

   After:

   ```
   use Symfony\Component\Form\Test\TypeTestCase;

   class MyTypeTest extends TypeTestCase
   {
       // ...
   }
   ```

 * The `FormIntegrationTestCase` and `FormPerformanceTestCase` classes were moved form the `Symfony\Component\Form\Tests` namespace to the `Symfony\Component\Form\Test` namespace.

 * The constants `ROUND_HALFEVEN`, `ROUND_HALFUP` and `ROUND_HALFDOWN` in class
   `NumberToLocalizedStringTransformer` were renamed to `ROUND_HALF_EVEN`,
   `ROUND_HALF_UP` and `ROUND_HALF_DOWN`.

 * The methods `ChoiceListInterface::getIndicesForChoices()` and
   `ChoiceListInterface::getIndicesForValues()` were removed. No direct
   replacement exists, although in most cases
   `ChoiceListInterface::getChoicesForValues()` and
   `ChoiceListInterface::getValuesForChoices()` should be sufficient.

 * The interface `Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface`
   and all of its implementations were removed. Use the new interface
   `Symfony\Component\Security\Csrf\CsrfTokenManagerInterface` instead.

 * The options "csrf_provider" and "intention" were renamed to "csrf_token_generator"
   and "csrf_token_id".

 * The method `Form::getErrorsAsString()` was removed. Use `Form::getErrors()`
   instead with the argument `$deep` set to true and `$flatten` set to false
   and cast the returned iterator to a string (if not done implicitly by PHP).

   Before:

   ```
   echo $form->getErrorsAsString();
   ```

   After:

   ```
   echo $form->getErrors(true, false);
   ```


### FrameworkBundle

 * The `getRequest` method of the base `Controller` class has been deprecated
   since Symfony 2.4 and must be therefore removed in 3.0. The only reliable
   way to get the `Request` object is to inject it in the action method.

   Before:

   ```
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

   ```
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

 * The `request` service was removed. You must inject the `request_stack`
   service instead.

 * The `enctype` method of the `form` helper was removed. You should use the
   new method `start` instead.

   Before:

   ```
   <form method="post" action="http://example.com" <?php echo $view['form']->enctype($form) ?>>
       ...
   </form>
   ```

   After:

   ```
   <?php echo $view['form']->start($form) ?>
       ...
   <?php echo $view['form']->end($form) ?>
   ```

   The method and action of the form default to "POST" and the current
   document. If you want to change these values, you can set them explicitly in
   the controller.

   Alternative 1:

   ```
   $form = $this->createForm('my_form', $formData, array(
       'method' => 'PUT',
       'action' => $this->generateUrl('target_route'),
   ));
   ```

   Alternative 2:

   ```
   $form = $this->createFormBuilder($formData)
       // ...
       ->setMethod('PUT')
       ->setAction($this->generateUrl('target_route'))
       ->getForm();
   ```

   It is also possible to override the method and the action in the template:

   ```
   <?php echo $view['form']->start($form, array('method' => 'GET', 'action' => 'http://example.com')) ?>
       ...
   <?php echo $view['form']->end($form) ?>
   ```

 * The `RouterApacheDumperCommand` was removed.

### HttpKernel

 * The `Symfony\Component\HttpKernel\Log\LoggerInterface` has been removed in
   favor of `Psr\Log\LoggerInterface`. The only difference is that some method
   names are different:

     * `emerg()` -> `emergency()`
     * `crit()`  -> `critical()`
     * `err()`   -> `error()`
     * `warn()`  -> `warning()`

   The previous method renames also happened to the following classes:

     * `Symfony\Bridge\Monolog\Logger`
     * `Symfony\Component\HttpKernel\Log\NullLogger`

 * The `Symfony\Component\HttpKernel\Kernel::init()` method has been removed.

 * The following classes have been renamed as they have been moved to the
   Debug component:

    * `Symfony\Component\HttpKernel\Debug\ErrorHandler` -> `Symfony\Component\Debug\ErrorHandler`
    * `Symfony\Component\HttpKernel\Debug\ExceptionHandler` -> `Symfony\Component\Debug\ExceptionHandler`
    * `Symfony\Component\HttpKernel\Exception\FatalErrorException` -> `Symfony\Component\Debug\Exception\FatalErrorException`
    * `Symfony\Component\HttpKernel\Exception\FlattenException` -> `Symfony\Component\Debug\Exception\FlattenException`

 * The `Symfony\Component\HttpKernel\EventListener\ExceptionListener` now
   passes the Request format as the `_format` argument instead of `format`.

 * The `Symfony\Component\HttpKernel\DependencyInjection\RegisterListenersPass` has been renamed to
   `Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass` and moved to the EventDispatcher component.

### Locale

 * The Locale component was removed and replaced by the Intl component.
   Instead of the methods in `Symfony\Component\Locale\Locale`, you should use
   these equivalent methods in `Symfony\Component\Intl\Intl` now:

    * `Locale::getDisplayCountries()` -> `Intl::getRegionBundle()->getCountryNames()`
    * `Locale::getCountries()` -> `array_keys(Intl::getRegionBundle()->getCountryNames())`
    * `Locale::getDisplayLanguages()` -> `Intl::getLanguageBundle()->getLanguageNames()`
    * `Locale::getLanguages()` -> `array_keys(Intl::getLanguageBundle()->getLanguageNames())`
    * `Locale::getDisplayLocales()` -> `Intl::getLocaleBundle()->getLocaleNames()`
    * `Locale::getLocales()` -> `array_keys(Intl::getLocaleBundle()->getLocaleNames())`

### PropertyAccess

 * Renamed `PropertyAccess::getPropertyAccessor` to `createPropertyAccessor`.

   Before:

   ```
   use Symfony\Component\PropertyAccess\PropertyAccess;

   $accessor = PropertyAccess::getPropertyAccessor();
   ```

   After:

   ```
   use Symfony\Component\PropertyAccess\PropertyAccess;

   $accessor = PropertyAccess::createPropertyAccessor();
   ```

### Routing

 * Some route settings have been renamed:

     * The `pattern` setting for a route has been deprecated in favor of `path`
     * The `_scheme` and `_method` requirements have been moved to the `schemes` and `methods` settings

   Before:

   ```
   article_edit:
       pattern: /article/{id}
       requirements: { '_method': 'POST|PUT', '_scheme': 'https', 'id': '\d+' }

   <route id="article_edit" pattern="/article/{id}">
       <requirement key="_method">POST|PUT</requirement>
       <requirement key="_scheme">https</requirement>
       <requirement key="id">\d+</requirement>
   </route>

   $route = new Route();
   $route->setPattern('/article/{id}');
   $route->setRequirement('_method', 'POST|PUT');
   $route->setRequirement('_scheme', 'https');
   ```

   After:

   ```
   article_edit:
       path: /article/{id}
       methods: [POST, PUT]
       schemes: https
       requirements: { 'id': '\d+' }

   <route id="article_edit" path="/article/{id}" methods="POST PUT" schemes="https">
       <requirement key="id">\d+</requirement>
   </route>

   $route = new Route();
   $route->setPath('/article/{id}');
   $route->setMethods(array('POST', 'PUT'));
   $route->setSchemes('https');
   ```

 * The `ApacheMatcherDumper` and `ApacheUrlMatcher` were removed since
   the performance gains were minimal and it's hard to replicate the behaviour
   of PHP implementation.

### Security

 * The `Resources/` directory was moved to `Core/Resources/`

### Translator

 * The `Translator::setFallbackLocale()` method has been removed in favor of
   `Translator::setFallbackLocales()`.

### Twig Bridge

 * The `render` tag is deprecated in favor of the `render` function.

 * The `form_enctype` helper was removed. You should use the new `form_start`
   function instead.

   Before:

   ```
   <form method="post" action="http://example.com" {{ form_enctype(form) }}>
       ...
   </form>
   ```

   After:

   ```
   {{ form_start(form) }}
       ...
   {{ form_end(form) }}
   ```

   The method and action of the form default to "POST" and the current
   document. If you want to change these values, you can set them explicitly in
   the controller.

   Alternative 1:

   ```
   $form = $this->createForm('my_form', $formData, array(
       'method' => 'PUT',
       'action' => $this->generateUrl('target_route'),
   ));
   ```

   Alternative 2:

   ```
   $form = $this->createFormBuilder($formData)
       // ...
       ->setMethod('PUT')
       ->setAction($this->generateUrl('target_route'))
       ->getForm();
   ```

   It is also possible to override the method and the action in the template:

   ```
   {{ form_start(form, {'method': 'GET', 'action': 'http://example.com'}) }}
       ...
   {{ form_end(form) }}
   ```

### Validator

 * The class `Symfony\Component\Validator\Mapping\Cache\ApcCache` has been removed in favor
   of `Symfony\Component\Validator\Mapping\Cache\DoctrineCache`.

   Before:

   ```
   use Symfony\Component\Validator\Mapping\Cache\ApcCache;

   $cache = new ApcCache('symfony.validator');
   ```

   After:

   ```
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

   ```
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

   ```
   use Symfony\Component\Validator\Constraints as Assert;

   /**
    * @Assert\Collection({
    *     "foo" = @Assert\Required(),
    *     "bar" = @Assert\Optional(),
    * })
    */
   private $property;
   ```

 * The option "methods" of the `Callback` constraint was removed. You should
   use the option "callback" instead. If you have multiple callbacks, add
   multiple callback constraints instead.

   Before (YAML):

   ```
   constraints:
     - Callback: [firstCallback, secondCallback]
   ```

   After (YAML):

   ```
   constraints:
     - Callback: firstCallback
     - Callback: secondCallback
   ```

   When using annotations, you can now put the Callback constraint directly on
   the method that should be executed.

   Before (Annotations):

   ```
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

   ```
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

   ```
   $validator->validate($object, 'Strict');

   $validator->validateValue($value, new NotNull());
   ```

   After:

   ```
   $validator->validate($object, null, 'Strict');

   $validator->validate($value, new NotNull());
   ```

   Apart from this change, the new methods `startContext()` and `inContext()`
   were added. The first of them allows to run multiple validations in the
   same context and aggregate their violations:

   ```
   $violations = $validator->startContext()
       ->atPath('firstName')->validate($firstName, new NotNull())
       ->atPath('age')->validate($age, new Type('integer'))
       ->getViolations();
   ```

   The second allows to run validation in an existing context. This is
   especially useful when calling the validator from within constraint
   validators:

   ```
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

   ```
   use Symfony\Component\Validator\MetadataInterface;
   ```

   After:

   ```
   use Symfony\Component\Validator\Mapping\MetadataInterface;
   ```

   The methods `getCascadingStrategy()` and `getTraversalStrategy()` were
   added to the interface. The first method should return a bit mask of the
   constants in class `CascadingStrategy`. The second should return a bit
   mask of the constants in `TraversalStrategy`.

   Example:

   ```
   use Symfony\Component\Validator\Mapping\TraversalStrategy;

   public function getTraversalStrategy()
   {
       return TraversalStrategy::TRAVERSE;
   }
   ```

 * The interface `PropertyMetadataInterface` was moved to the `Mapping`
   namespace.

   Before:

   ```
   use Symfony\Component\Validator\PropertyMetadataInterface;
   ```

   After:

   ```
   use Symfony\Component\Validator\Mapping\PropertyMetadataInterface;
   ```

 * The interface `PropertyMetadataContainerInterface` was moved to the `Mapping`
   namespace and renamed to `ClassMetadataInterface`.

   Before:

   ```
   use Symfony\Component\Validator\PropertyMetadataContainerInterface;
   ```

   After:

   ```
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

   ```
   use Symfony\Component\Validator\ClassBasedInterface;

   class MyClassMetadata implements ClassBasedInterface
   {
       // ...
   }
   ```

   After:

   ```
   use Symfony\Component\Validator\Mapping\ClassMetadataInterface;

   class MyClassMetadata implements ClassMetadataInterface
   {
       // ...
   }
   ```

 * The class `ElementMetadata` was renamed to `GenericMetadata`.

   Before:

   ```
   use Symfony\Component\Validator\Mapping\ElementMetadata;

   class MyMetadata extends ElementMetadata
   {
   }
   ```

   After:

   ```
   use Symfony\Component\Validator\Mapping\GenericMetadata;

   class MyMetadata extends GenericMetadata
   {
   }
   ```

 * The interface `ExecutionContextInterface` and its implementation
   `ExecutionContext` were moved to the `Context` namespace.

   Before:

   ```
   use Symfony\Component\Validator\ExecutionContextInterface;
   ```

   After:

   ```
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

   ```
   $context->addViolationAt('property', 'The value {{ value }} is invalid.', array(
       '{{ value }}' => $invalidValue,
   ));
   ```

   After:

   ```
   $context->buildViolation('The value {{ value }} is invalid.')
       ->atPath('property')
       ->setParameter('{{ value }}', $invalidValue)
       ->addViolation();
   ```

   The methods `validate()` and `validateValue()` were removed. You should use
   `getValidator()` together with `inContext()` instead.

   Before:

   ```
   $context->validate($object);
   ```

   After:

   ```
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

   ```
   $metadata = $context->getMetadataFactory()->getMetadataFor($myClass);
   ```

   After:

   ```
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

   ```
   use Symfony\Component\Validator\Mapping\ClassMetadataFactory;

   $factory = new ClassMetadataFactory($loader);
   ```

   After:

   ```
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

   ```
   $parameters = $violation->getMessageParameters();
   $plural = $violation->getMessagePluralization();
   ```

   After:

   ```
   $parameters = $violation->getParameters();
   $plural = $violation->getPlural();
   ```

### Yaml

 * The ability to pass file names to `Yaml::parse()` has been removed.

   Before:

   ```
   Yaml::parse($fileName);
   ```

   After:

   ```
   Yaml::parse(file_get_contents($fileName));

### Process

 * Process::setStdin() and Process::getStdin() have been removed. Use
   Process::setInput() and Process::getInput() that works the same way.
 * Process::setInput() and ProcessBuilder::setInput() do not accept non-scalar types.
