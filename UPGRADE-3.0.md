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

### Yaml

 * The ability to pass file names to `Yaml::parse()` has been removed.

   Before:

   ```
   Yaml::parse($fileName);
   ```

   After:

   ```
   Yaml::parse(file_get_contents($fileName));
   ```
