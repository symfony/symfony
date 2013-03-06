UPGRADE FROM 2.1 to 2.2
=======================

### TwigBridge

 * The `render` tag signature and arguments changed.

   Before:

   ```
   {% render 'BlogBundle:Post:list' with { 'limit': 2 }, { 'alt': 'BlogBundle:Post:error' } %}
   ```

   After:

   ```
   {% render controller('BlogBundle:Post:list', { 'limit': 2 }), { 'alt': 'BlogBundle:Post:error' } %}
   {# Or: #}
   {{ render(controller('BlogBundle:Post:list', { 'limit': 2 }), { 'alt': 'BlogBundle:Post:error'}) }}
   ```

   Note: The function is the preferred way.

### HttpFoundation

 * The MongoDbSessionHandler default field names and timestamp type have changed.

   The `sess_` prefix was removed from default field names. The session ID is
   now stored in the `_id` field by default. The session date is now stored as a
   `MongoDate` instead of `MongoTimestamp`, which also makes it possible to use
   TTL collections in MongoDB 2.2+ instead of relying on the `gc()` method.

 * The Stopwatch functionality was moved from HttpKernel\Debug to its own component

 * The _method request parameter support has been disabled by default (call
   Request::enableHttpMethodParameterOverride() to enable it).

#### Deprecations

 * The `Request::splitHttpAcceptHeader()` is deprecated and will be removed in 2.3.

   You should now use the `AcceptHeader` class which give you fluent methods to
   parse request accept-* headers. Some examples:

   ```
   $accept = AcceptHeader::fromString($request->headers->get('Accept'));
   if ($accept->has('text/html') {
       $item = $accept->get('html');
       $charset = $item->getAttribute('charset', 'utf-8');
       $quality = $item->getQuality();
   }

   // accepts items are sorted by descending quality
   $accepts = AcceptHeader::fromString($request->headers->get('Accept'))->all();

   ```

### Form

 * The PasswordType is now not trimmed by default.

 * The class FormException is now an interface. The old class is still available
   under the name Symfony\Component\Form\Exception\Exception, but will probably
   be removed before 2.2. If you created FormException instances manually,
   you are now advised to create any of the other exceptions in the
   Symfony\Component\Form\Exception namespace or to create custom exception
   classes for your purpose.

 * Translating validation errors is now optional. You can still do so
   manually if you like, or you can simplify your templates to simply output
   the already translated message.

   Before:

   ```
   {{
       error.messagePluralization is null
           ? error.messageTemplate|trans(error.messageParameters, 'validators')
           : error.messageTemplate|transchoice(error.messagePluralization, error.messageParameters, 'validators')
   }}
   ```

   After:

   ```
   {{ error.message }}
   ```

 * FormType, ModelType and PropertyPathMapper now have constructors. If you
   extended these classes, you should call the parent constructor now.
   Note that you are not recommended to extend FormType nor ModelType. You should
   extend AbstractType instead and use the Form component's own inheritance
   mechanism (`AbstractType::getParent()`).

   Before:

   ```
   use Symfony\Component\Form\Extensions\Core\DataMapper\PropertyPathMapper;

   class CustomMapper extends PropertyPathMapper
   {
       public function __construct()
       {
           // ...
       }

       // ...
   }
   ```

   After:

   ```
   use Symfony\Component\Form\Extensions\Core\DataMapper\PropertyPathMapper;

   class CustomMapper extends PropertyPathMapper
   {
       public function __construct()
       {
           parent::__construct();

           // ...
       }

       // ...
   }
   ```

#### Deprecations

 * The methods `getParent()`, `setParent()` and `hasParent()` in
   `FormBuilderInterface` were deprecated and will be removed in Symfony 2.3.
   You should not rely on these methods in your form type because the parent
   of a form can change after building it.

 * The class PropertyPath and related classes were deprecated and moved to a
   dedicated component PropertyAccess. If you used any of these classes or
   interfaces, you should adapt the namespaces now. During the move,
   InvalidPropertyException was renamed to NoSuchPropertyException.

   Before:

   ```
   use Symfony\Component\Form\Util\PropertyPath;
   use Symfony\Component\Form\Util\PropertyPathBuilder;
   use Symfony\Component\Form\Util\PropertyPathInterface;
   use Symfony\Component\Form\Util\PropertyPathIterator;
   use Symfony\Component\Form\Util\PropertyPathIteratorInterface;
   use Symfony\Component\Form\Exception\InvalidPropertyException;
   use Symfony\Component\Form\Exception\InvalidPropertyPathException;
   use Symfony\Component\Form\Exception\PropertyAccessDeniedException;
   ```

   After:

   ```
   use Symfony\Component\PropertyAccess\PropertyPath;
   use Symfony\Component\PropertyAccess\PropertyPathBuilder;
   use Symfony\Component\PropertyAccess\PropertyPathInterface;
   use Symfony\Component\PropertyAccess\PropertyPathIterator;
   use Symfony\Component\PropertyAccess\PropertyPathIteratorInterface;
   use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
   use Symfony\Component\PropertyAccess\Exception\InvalidPropertyPathException;
   use Symfony\Component\PropertyAccess\Exception\PropertyAccessDeniedException;
   ```

   Also, `FormUtil::singularify()` was split away into a class StringUtil
   in the new component.

   Before:

   ```
   use Symfony\Component\Form\Util\FormUtil;

   $singular = FormUtil::singularify($plural);
   ```

   After:

   ```
   use Symfony\Component\PropertyAccess\StringUtil;

   $singular = StringUtil::singularify($plural);
   ```

   The methods `getValue()` and `setValue()` were moved to a new class
   PropertyAccessor.

   Before:

   ```
   use Symfony\Component\Form\Util\PropertyPath;

   $propertyPath = new PropertyPath('some.path');

   $value = $propertyPath->getValue($object);
   $propertyPath->setValue($object, 'new value');
   ```

   After (alternative 1):

   ```
   use Symfony\Component\PropertyAccess\PropertyAccess;

   $propertyAccessor = PropertyAccess::getPropertyAccessor();

   $value = $propertyAccessor->getValue($object, 'some.path');
   $propertyAccessor->setValue($object, 'some.path', 'new value');
   ```

   After (alternative 2):

   ```
   use Symfony\Component\PropertyAccess\PropertyAccess;
   use Symfony\Component\PropertyAccess\PropertyPath;

   $propertyAccessor = PropertyAccess::getPropertyAccessor();
   $propertyPath = new PropertyPath('some.path');

   $value = $propertyAccessor->getValue($object, $propertyPath);
   $propertyAccessor->setValue($object, $propertyPath, 'new value');
   ```

### Routing

 * RouteCollection does not behave like a tree structure anymore but as a flat
   array of Routes. So when using PHP to build the RouteCollection, you must
   make sure to add routes to the sub-collection before adding it to the parent
   collection (this is not relevant when using YAML or XML for Route definitions).

   Before:

   ```
   $rootCollection = new RouteCollection();
   $subCollection = new RouteCollection();
   $rootCollection->addCollection($subCollection);
   $subCollection->add('foo', new Route('/foo'));
   ```

   After:

   ```
   $rootCollection = new RouteCollection();
   $subCollection = new RouteCollection();
   $subCollection->add('foo', new Route('/foo'));
   $rootCollection->addCollection($subCollection);
   ```

   Also one must call `addCollection` from the bottom to the top hierarchy.
   So the correct sequence is the following (and not the reverse):

   ```
   $childCollection->addCollection($grandchildCollection);
   $rootCollection->addCollection($childCollection);
   ```

 * The methods `RouteCollection::getParent()` and `RouteCollection::getRoot()`
   have been deprecated and will be removed in Symfony 2.3.
 * Misusing the `RouteCollection::addPrefix` method to add defaults, requirements
   or options without adding a prefix is not supported anymore. So if you called `addPrefix`
   with an empty prefix or `/` only (both have no relevance), like
   `addPrefix('', $defaultsArray, $requirementsArray, $optionsArray)`
   you need to use the new dedicated methods `addDefaults($defaultsArray)`,
   `addRequirements($requirementsArray)` or `addOptions($optionsArray)` instead.
 * The `$options` parameter to `RouteCollection::addPrefix()` has been deprecated
   because adding options has nothing to do with adding a path prefix. If you want to add options
   to all child routes of a RouteCollection, you can use `addOptions()`.
 * The method `RouteCollection::getPrefix()` has been deprecated
   because it suggested that all routes in the collection would have this prefix, which is
   not necessarily true. On top of that, since there is no tree structure anymore, this method
   is also useless.
 * `RouteCollection::addCollection(RouteCollection $collection)` should now only be
   used with a single parameter. The other params `$prefix`, `$default`, `$requirements` and `$options`
   will still work, but have been deprecated. The `addPrefix` method should be used for this
   use-case instead.
   Before: `$parentCollection->addCollection($collection, '/prefix', array(...), array(...))`
   After:
   ```
   $collection->addPrefix('/prefix', array(...), array(...));
   $parentCollection->addCollection($collection);
   ```

### Validator

 * Interfaces were created for the classes `ConstraintViolation`,
   `ConstraintViolationList`, `GlobalExecutionContext` and `ExecutionContext`.
   If you type hinted against any of these classes, you are recommended to
   type hint against their interfaces now.

   Before:

   ```
   use Symfony\Component\Validator\ExecutionContext;

   public function validateCustomLogic(ExecutionContext $context)
   ```

   After:

   ```
   use Symfony\Component\Validator\ExecutionContextInterface;

   public function validateCustomLogic(ExecutionContextInterface $context)
   ```

   For all implementations of `ConstraintValidatorInterface`, this change is
   mandatory for the `initialize` method:

   Before:

   ```
   use Symfony\Component\Validator\ConstraintValidatorInterface;
   use Symfony\Component\Validator\ExecutionContext;

   class MyValidator implements ConstraintValidatorInterface
   {
       public function initialize(ExecutionContext $context)
       {
           // ...
       }
   }
   ```

   After:

   ```
   use Symfony\Component\Validator\ConstraintValidatorInterface;
   use Symfony\Component\Validator\ExecutionContextInterface;

   class MyValidator implements ConstraintValidatorInterface
   {
       public function initialize(ExecutionContextInterface $context)
       {
           // ...
       }
   }
   ```

 * The sources of the pluralized messages in translation files have changed
   from the singular to the pluralized version. If you created custom
   translation files for validator errors, you should adapt them.

   Before:

   <trans-unit id="6">
       <source>You must select at least {{ limit }} choices.</source>
       <target>Sie müssen mindestens {{ limit }} Möglichkeit wählen.|Sie müssen mindestens {{ limit }} Möglichkeiten wählen.</target>
   </trans-unit>

   After:

   <trans-unit id="6">
       <source>You must select at least {{ limit }} choice.|You must select at least {{ limit }} choices.</source>
       <target>Sie müssen mindestens {{ limit }} Möglichkeit wählen.|Sie müssen mindestens {{ limit }} Möglichkeiten wählen.</target>
   </trans-unit>

   Check the file src/Symfony/Component/Validator/Resources/translations/validators.en.xlf
   for the new message sources.

#### Deprecations

 * The interface `ClassMetadataFactoryInterface` was deprecated and will be
   removed in Symfony 2.3. You should implement `MetadataFactoryInterface`
   instead, which changes the name of the method `getClassMetadata` to
   `getMetadataFor` and accepts arbitrary values (e.g. class names, objects,
   numbers etc.). In your implementation, you should throw a
   `NoSuchMetadataException` if you don't support metadata for the given value.

   Before:

   ```
   use Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface;

   class MyMetadataFactory implements ClassMetadataFactoryInterface
   {
       public function getClassMetadata($class)
       {
           // ...
       }
   }
   ```

   After:

   ```
   use Symfony\Component\Validator\MetadataFactoryInterface;
   use Symfony\Component\Validator\Exception\NoSuchMetadataException;

   class MyMetadataFactory implements MetadataFactoryInterface
   {
       public function getMetadataFor($value)
       {
           if (is_object($value)) {
               $value = get_class($value);
           }

           if (!is_string($value) || (!class_exists($value) && !interface_exists($value))) {
               throw new NoSuchMetadataException(...);
           }

           // ...
       }
   }
   ```

   The return value of `ValidatorInterface::getMetadataFactory()` was also
   changed to return `MetadataFactoryInterface`. Make sure to replace calls to
   `getClassMetadata` by `getMetadataFor` on the return value of this method.

   Before:

   ```
   $metadataFactory = $validator->getMetadataFactory();
   $metadata = $metadataFactory->getClassMetadata('Vendor\MyClass');
   ```

   After:

   ```
   $metadataFactory = $validator->getMetadataFactory();
   $metadata = $metadataFactory->getMetadataFor('Vendor\MyClass');
   ```

 * The class `GraphWalker` and the accessor `ExecutionContext::getGraphWalker()`
   were deprecated and will be removed in Symfony 2.3. You should use the
   methods `ExecutionContextInterface::validate()` and
   `ExecutionContextInterface::validateValue()` instead.

   Before:

   ```
   use Symfony\Component\Validator\ExecutionContext;

   public function validateCustomLogic(ExecutionContext $context)
   {
       if (/* ... */) {
           $path = $context->getPropertyPath();
           $group = $context->getGroup();

           if (!empty($path)) {
               $path .= '.';
           }

           $context->getGraphWalker()->walkReference($someObject, $group, $path . 'myProperty', false);
       }
   }
   ```

   After:

   ```
   use Symfony\Component\Validator\ExecutionContextInterface;

   public function validateCustomLogic(ExecutionContextInterface $context)
   {
       if (/* ... */) {
           $context->validate($someObject, 'myProperty');
       }
   }
   ```

 * The method `ExecutionContext::addViolationAtSubPath()` was deprecated and
   will be removed in Symfony 2.3. You should use `addViolationAt()` instead.

   Before:

   ```
   use Symfony\Component\Validator\ExecutionContext;

   public function validateCustomLogic(ExecutionContext $context)
   {
       if (/* ... */) {
           $context->addViolationAtSubPath('myProperty', 'This value is invalid');
       }
   }
   ```

   After:

   ```
   use Symfony\Component\Validator\ExecutionContextInterface;

   public function validateCustomLogic(ExecutionContextInterface $context)
   {
       if (/* ... */) {
           $context->addViolationAt('myProperty', 'This value is invalid');
       }
   }
   ```

 * The methods `ExecutionContext::getCurrentClass()`, `ExecutionContext::getCurrentProperty()`
   and `ExecutionContext::getCurrentValue()` were deprecated and will be removed
   in Symfony 2.3. Use the methods `getClassName()`, `getPropertyName()` and
   `getValue()` instead.

   Before:

   ```
   use Symfony\Component\Validator\ExecutionContext;

   public function validateCustomLogic(ExecutionContext $context)
   {
       $class = $context->getCurrentClass();
       $property = $context->getCurrentProperty();
       $value = $context->getCurrentValue();

       // ...
   }
   ```

   After:

   ```
   use Symfony\Component\Validator\ExecutionContextInterface;

   public function validateCustomLogic(ExecutionContextInterface $context)
   {
       $class = $context->getClassName();
       $property = $context->getPropertyName();
       $value = $context->getValue();

       // ...
   }
   ```

### FrameworkBundle

 * The `render` method of the `actions` templating helper signature and arguments changed:

   Before:

   ```
   <?php echo $view['actions']->render('BlogBundle:Post:list', array('limit' => 2), array('alt' => 'BlogBundle:Post:error')) ?>
   ```

   After:

   ```
   <?php echo $view['actions']->render($view['router']->generate('post_list', array('limit' => 2)), array('alt' => 'BlogBundle:Post:error')) ?>
   ```

   where `post_list` is the route name for the `BlogBundle:Post:list`
   controller, or if you don't want to create a route:

   ```
   <?php echo $view['actions']->render(new ControllerReference('BlogBundle:Post:list', array('limit' => 2)), array('alt' => 'BlogBundle:Post:error')) ?>
   ```

#### Configuration

 * The 2.2 version introduces a new parameter `trusted_proxies` that replaces
   `trust_proxy_headers` in the framework configuration.

   Before:

   ```
   # app/config/config.yml
   framework:
       trust_proxy_headers: false
   ```

   After:

   ```
   # app/config/config.yml
   framework:
      trusted_proxies: ['127.0.0.1', '10.0.0.1'] # a list of proxy IPs you trust
   ```

### Security

  * The existing ``UserPassword`` validator constraint class has been modified.
    Its namespace has been changed to better fit the Symfony coding conventions.

    Before:

    ```
    use Symfony\Component\Security\Core\Validator\Constraint\UserPassword;
    ```

    After: (note the `s` at the end of `Constraint`)

    ```
    use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
    ```

  * The new ``UserPassword`` validator constraint class now accepts a new
    ``service`` option that allows to specify a custom validator service name in
    order to validate the current logged-in user's password.

    ```
    use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;

    $constraint = new UserPassword(array(
        'service' => 'my.custom.validator.user_password',
    ));
    ```

#### Deprecations

  * The two previous ``UserPassword`` and ``UserPasswordValidator`` classes in
    the ``Symfony\Component\Security\Core\Validator\Constraint`` namespace have
    been deprecated and will be removed in 2.3.

    Before:

    ```
    use Symfony\Component\Security\Core\Validator\Constraint\UserPassword;
    use Symfony\Component\Security\Core\Validator\Constraint\UserPasswordValidator;
    ```

    After:

    ```
    use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
    use Symfony\Component\Security\Core\Validator\Constraints\UserPasswordValidator;
    ```

### Serializer

 * All serializer interfaces (Serializer, Normalizer, Encoder) have been
   extended with an optional `$context` array. This was necessary to allow for
   more complex use-cases that require context information during the
   (de)normalization and en-/decoding steps.
