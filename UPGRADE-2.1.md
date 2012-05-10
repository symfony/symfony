UPGRADE FROM 2.0 to 2.1
=======================

### General

  * The merging strategy for `assets_base_urls` and `base_urls` has changed.

    Unlike most configuration blocks, successive values for `assets_base_urls`
    will overwrite each other instead of being merged. This behavior was chosen
    because developers will typically define base URL's for each environment.
    Given that most projects tend to inherit configurations (e.g.
    `config_test.yml` imports `config_dev.yml`) and/or share a common base
    configuration (i.e. `config.yml`), merging could yield a set of base URL's
    for multiple environments.

### Doctrine

    The DoctrineBundle is moved from the Symfony repository to the Doctrine repository.
    Therefore you should change the namespace of this bundle in your AppKernel.php:

    Before: `new Symfony\Bundle\DoctrineBundle\DoctrineBundle()`
    After: `new Doctrine\Bundle\DoctrineBundle\DoctrineBundle()`

### HttpFoundation

  * Locale management was moved from the Session class to the Request class.

    ##### Configuring the default locale

    Before:

    ```
    framework:
        session:
            default_locale: fr
    ```

    After:

    ```
    framework:
        default_locale: fr
    ```

  * The methods `getPathInfo()`, `getBaseUrl()` and `getBasePath()` of
    a `Request` now all return a raw value (vs a urldecoded value before). Any call
    to one of these methods must be checked and wrapped in a `rawurldecode()` if
    needed.

    ##### Retrieving the locale from a Twig template

    Before: `{{ app.request.session.locale }}` or `{{ app.session.locale }}`

    After: `{{ app.request.locale }}`

    ##### Retrieving the locale from a PHP template

    Before: `$view['session']->getLocale()`

    After: `$view['request']->getLocale()`

    ##### Retrieving the locale from PHP code

    Before: `$session->getLocale()`

    After: `$request->getLocale()`

### Security

  * `Symfony\Component\Security\Core\User\UserInterface::equals()` has moved to
    `Symfony\Component\Security\Core\User\EquatableInterface::isEqualTo()`.

    You must rename the `equals()` method in your implementation of the `User`
    class to `isEqualTo()` and implement `EquatableInterface`. Apart from that,
    no other changes are required.

    Alternatively, you may use the default implementation provided by
    `AbstractToken::hasUserChanged()` if you have no need of custom comparison
    logic. In this case, do not implement `EquatableInterface` and remove your
    comparison method.

    Before:

    ```
    class User implements UserInterface
    {
        // ...
        public function equals(UserInterface $user) { /* ... */ }
        // ...
    }
```

    After:

    ```
    class User implements UserInterface, EquatableInterface
    {
        // ...
        public function isEqualTo(UserInterface $user) { /* ... */ }
        // ...
    }
    ```
  * The custom factories for the firewall configuration are now
    registered during the build method of bundles instead of being registered
    by the end-user. This means that you will you need to remove the 'factories'
    keys in your security configuration.

  * The Firewall listener is now registered after the Router listener. This
    means that specific Firewall URLs (like /login_check and /logout) must now
    have proper routes defined in your routing configuration.

  * The user provider configuration has been refactored. The configuration
    for the chain provider and the memory provider has been changed:

     Before:

     ``` yaml
     security:
         providers:
             my_chain_provider:
                 providers: [my_memory_provider, my_doctrine_provider]
             my_memory_provider:
                 users:
                     toto: { password: foobar, roles: [ROLE_USER] }
                     foo: { password: bar, roles: [ROLE_USER, ROLE_ADMIN] }
     ```

     After:

     ``` yaml
     security:
         providers:
             my_chain_provider:
                 chain:
                     providers: [my_memory_provider, my_doctrine_provider]
             my_memory_provider:
                 memory:
                     users:
                         toto: { password: foobar, roles: [ROLE_USER] }
                         foo: { password: bar, roles: [ROLE_USER, ROLE_ADMIN] }
     ```

  * `MutableAclInterface::setParentAcl` now accepts `null`, review any
    implementations of this interface to reflect this change.

### Form

  * Child forms are no longer automatically validated. That means that you must
    explicitly set the `Valid` constraint in your model if you want to validate
    associated objects.

    If you don't want to set the `Valid` constraint, or if there is no reference
    from the data of the parent form to the data of the child form, you can
    enable BC behavior by setting the `cascade_validation` form option to `true`
    on the parent form.

  * Changed implementation of choice lists

    ArrayChoiceList was replaced. If you have custom classes that extend this
    class, you must now extend SimpleChoiceList.

    Before:

    ```
    class MyChoiceList extends ArrayChoiceList
    {
        protected function load()
        {
            parent::load();

            // load choices

            $this->choices = $choices;
        }
    }
    ```

    After:

    ```
    class MyChoiceList extends SimpleChoiceList
    {
        public function __construct()
        {
            // load choices

            parent::__construct($choices);
        }
    }
    ```

    If you need to load the choices lazily -- that is, as soon as they are
    accessed for the first time -- you can extend LazyChoiceList instead.

    ```
    class MyChoiceList extends LazyChoiceList
    {
        protected function loadChoiceList()
        {
            // load choices

            return new SimpleChoiceList($choices);
        }
    }
    ```

    PaddedChoiceList, MonthChoiceList and TimezoneChoiceList were removed.
    Their functionality was merged into DateType, TimeType and TimezoneType.

    EntityChoiceList was adapted. The methods `getEntities()`,
    `getEntitiesByKeys()`, `getIdentifier()` and `getIdentifierValues()` were
    removed or made private. Instead of the first two, you can now use
    `getChoices()` and `getChoicesByValues()`. For the latter two, no
    replacement exists.

  * The strategy for generating the `id` and `name` HTML attributes for
    checkboxes and radio buttons in a choice field has changed.

    Instead of appending the choice value, a generated integer is now appended
    by default. Take care if your JavaScript relies on that.

  * In the choice field type's template, the structure of the `choices` variable
    has changed.

    The `choices` variable now contains ChoiceView objects with two getters,
    `getValue()` and `getLabel()`, to access the choice data.

    Before:

    ```
    {% for choice, label in choices %}
        <option value="{{ choice }}"{% if _form_is_choice_selected(form, choice) %} selected="selected"{% endif %}>
            {{ label }}
        </option>
    {% endfor %}
    ```

    After:

    ```
    {% for choice in choices %}
        <option value="{{ choice.value }}"{% if _form_is_choice_selected(form, choice) %} selected="selected"{% endif %}>
            {{ choice.label }}
        </option>
    {% endfor %}
    ```

  * In the collection type's template, the default name of the prototype field
    has changed from `$$name$$` to `__name__`.

    For custom names, dollar signs are no longer prepended and appended. You are
    advised to prepend and append two underscores wherever you specify a value
    for the field's `prototype_name` option.

    Before:

    ```
    $builder->add('tags', 'collection', array('prototype' => 'proto'));

    // results in the name "$$proto$$" in the template
    ```

    After:

    ```
    $builder->add('tags', 'collection', array('prototype' => '__proto__'));

    // results in the name "__proto__" in the template
    ```

  * The `read_only` field attribute now renders as `readonly="readonly"`, use
    `disabled` instead for `disabled="disabled"`.

  * Form and field names must now start with a letter, digit or underscore
    and only contain letters, digits, underscores, hyphens and colons

  * `EntitiesToArrayTransformer` and `EntityToIdTransformer` have been removed.
    The former has been replaced by `CollectionToArrayTransformer` in combination
    with `EntityChoiceList`, the latter is not required in the core anymore.

  * The following transformers have been renamed:

      * `ArrayToBooleanChoicesTransformer` to `ChoicesToBooleanArrayTransformer`
      * `ScalarToBooleanChoicesTransformer` to `ChoiceToBooleanArrayTransformer`
      * `ArrayToChoicesTransformer` to `ChoicesToValuesTransformer`
      * `ScalarToChoiceTransformer` to `ChoiceToValueTransformer`

    to be consistent with the naming in `ChoiceListInterface`.

  * `FormUtil::toArrayKey()` and `FormUtil::toArrayKeys()` have been removed.
    They were merged into ChoiceList and have no public equivalent anymore.

  * The options passed to the `getParent()` method of form types no longer
    contain default options. They only contain the options passed by the user.

    You should check if options exist before attempting to read their value.

    Before:

    ```
    public function getParent(array $options)
    {
        return 'single_text' === $options['widget'] ? 'text' : 'choice';
    }
    ```

    After:

    ```
    public function getParent(array $options)
    {
        return isset($options['widget']) && 'single_text' === $options['widget'] ? 'text' : 'choice';
    }
    ```

  * The methods `getDefaultOptions()` and `getAllowedOptionValues()` of form
    types no longer receive an option array.

    You can specify options that depend on other options using closures instead.

    Before:

    ```
    public function getDefaultOptions(array $options)
    {
        $defaultOptions = array();

        if ($options['multiple']) {
            $defaultOptions['empty_data'] = array();
        }

        return $defaultOptions;
    }
    ```

    After:

    ```
    public function getDefaultOptions()
    {
        return array(
            'empty_data' => function (Options $options, $previousValue) {
                return $options['multiple'] ? array() : $previousValue;
            }
        );
    }
    ```

    The second argument `$previousValue` does not have to be specified if not
    needed.

  * The `add()`, `remove()`, `setParent()`, `bind()` and `setData()` methods in
    the Form class now throw an exception if the form is already bound.

    If you used these methods on bound forms, you should consider moving your
    logic to an event listener that observes one of the following events:
    `FormEvents::PRE_BIND`, `FormEvents::BIND_CLIENT_DATA` or
    `FormEvents::BIND_NORM_DATA`.

  * The interface FormValidatorInterface was deprecated and will be removed
    in Symfony 2.3.

    If you implemented custom validators using this interface, you can
    substitute them by event listeners listening to the FormEvents::POST_BIND
    (or any other of the BIND events). In case you used the CallbackValidator
    class, you should now pass the callback directly to `addEventListener`.

  * Since FormType and FieldType were merged, you need to adapt your form
    themes.

    The "field_widget" and all references to it should be renamed to
    "form_widget_single_control":

    Before:

    ```
    {% block url_widget %}
    {% spaceless %}
        {% set type = type|default('url') %}
        {{ block('field_widget') }}
    {% endspaceless %}
    {% endblock url_widget %}
    ```

    After:

    ```
    {% block url_widget %}
    {% spaceless %}
        {% set type = type|default('url') %}
        {{ block('form_widget_single_control') }}
    {% endspaceless %}
    {% endblock url_widget %}
    ```

    All other "field_*" blocks and references to them should be renamed to
    "form_*". If you previously defined both a "field_*" and a "form_*"
    block, you can merge them into a single "form_*" block and check the new
    Boolean variable "single_control":

    Before:

    ```
    {% block form_errors %}
    {% spaceless %}
        ... form code ...
    {% endspaceless %}
    {% endblock form_errors %}

    {% block field_errors %}
    {% spaceless %}
        ... field code ...
    {% endspaceless %}
    {% endblock field_errors %}
    ```

    After:

    ```
    {% block form_errors %}
    {% spaceless %}
        {% if single_control %}
            ... field code ...
        {% else %}
            ... form code ...
        {% endif %}
    {% endspaceless %}
    {% endblock form_errors %}
    ```

    Furthermore, the block "generic_label" was merged into "form_label". You
    should now override "form_label" in order to customize labels.

    Last but not least, the block "widget_choice_options" was renamed to
    "choice_widget_options" to be consistent with the rest of the default
    theme.

  * The method `guessMinLength()` of FormTypeGuesserInterface was deprecated
    and will be removed in Symfony 2.3. You should use the new method
    `guessPattern()` instead which may return any regular expression that
    is inserted in the HTML5 attribute "pattern".

    Before:

    public function guessMinLength($class, $property)
    {
        if (/* condition */) {
            return new ValueGuess($minLength, Guess::LOW_CONFIDENCE);
        }
    }

    After:

    public function guessPattern($class, $property)
    {
        if (/* condition */) {
            return new ValueGuess('.{' . $minLength . ',}', Guess::LOW_CONFIDENCE);
        }
    }

### Validator

  * The methods `setMessage()`, `getMessageTemplate()` and
    `getMessageParameters()` in the Constraint class were deprecated and will
    be removed in Symfony 2.3.

    If you have implemented custom validators, you should use the
    `addViolation()` method on the `ExecutionContext` object instead.

    Before:

    ```
    public function isValid($value, Constraint $constraint)
    {
        // ...
        if (!$valid) {
            $this->setMessage($constraint->message, array(
                '{{ value }}' => $value,
            ));

            return false;
        }
    }
    ```

    After:

    ```
    public function isValid($value, Constraint $constraint)
    {
        // ...
        if (!$valid) {
            $this->context->addViolation($constraint->message, array(
                '{{ value }}' => $value,
            ));

            return false;
        }
    }
    ```

  * The method `setPropertyPath()` in the ExecutionContext class
    was removed.

    You should use the `addViolationAtSubPath()` method on the
    `ExecutionContext` object instead.

    Before:

    ```
    public function isPropertyValid(ExecutionContext $context)
    {
        // ...
        $propertyPath = $context->getPropertyPath() . '.property';
        $context->setPropertyPath($propertyPath);
        $context->addViolation('Error Message', array(), null);
    }
    ```

    After:

    ```
    public function isPropertyValid(ExecutionContext $context)
    {
        // ...
        $context->addViolationAtSubPath('property', 'Error Message', array(), null);

    }
    ```

  * The method `isValid` of `ConstraintValidatorInterface` was renamed to
    `validate` and its return value was dropped.

    `ConstraintValidator` still contains the deprecated `isValid` method and
    forwards `validate` calls to `isValid` by default. This BC layer will be
    removed in Symfony 2.3. You are advised to rename your methods. You should
    also remove the return values, which have never been used by the framework.

    Before:

    ```
    public function isValid($value, Constraint $constraint)
    {
        // ...
        if (!$valid) {
            $this->context->addViolation($constraint->message, array(
                '{{ value }}' => $value,
            ));

            return false;
        }
    }
    ```

    After:

    ```
    public function validate($value, Constraint $constraint)
    {
        // ...
        if (!$valid) {
            $this->context->addViolation($constraint->message, array(
                '{{ value }}' => $value,
            ));

            return;
        }
    }
    ```

  * Core translation messages are changed. Dot is added at the end of each message.
    Overwritten core translations should be fixed if any. More info here.

### Session

  * Flash messages now return an array based on their type. The old method is
    still available but is now deprecated.

    ##### Retrieving the flash messages from a Twig template

    Before:

    ```
    {% if app.session.hasFlash('notice') %}
        <div class="flash-notice">
            {{ app.session.getFlash('notice') }}
        </div>
    {% endif %}
    ```
    After:

    ```
    {% for flashMessage in app.session.flashbag.get('notice') %}
        <div class="flash-notice">
            {{ flashMessage }}
        </div>
    {% endfor %}
    ```

    You can process all flash messages in a single loop with:

    ```
    {% for type, flashMessages in app.session.flashbag.all() %}
        {% for flashMessage in flashMessages %}
            <div class="flash-{{ type }}">
                {{ flashMessage }}
            </div>
        {% endfor %}
    {% endfor %}
    ```

  * Session handler drivers should implement `\SessionHandlerInterface` or extend from
    `Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeHandlerInterface` base class and renamed
    to `Handler\FooSessionHandler`.  E.g. `PdoSessionStorage` becomes `Handler\PdoSessionHandler`.

  * Refactor code using `$session->*flash*()` methods to use `$session->getFlashBag()->*()`.

### Serializer

 * The key names created by the  `GetSetMethodNormalizer` have changed from
    from all lowercased to camelCased (e.g. `mypropertyvalue` to `myPropertyValue`).

 * The `item` element is now converted to an array when deserializing XML.

    ``` xml
    <?xml version="1.0"?>
    <response>
        <item><title><![CDATA[title1]]></title></item><item><title><![CDATA[title2]]></title></item>
    </response>
    ```

    Before:

        Array()

    After:

        Array(
            [item] => Array(
                [0] => Array(
                    [title] => title1
                )
                [1] => Array(
                    [title] => title2
                )
            )
        )

### Routing

  * The UrlMatcher urldecodes the route parameters only once, they were
    decoded twice before. Note that the `urldecode()` calls have been changed for a
    single `rawurldecode()` in order to support `+` for input paths.

### FrameworkBundle

  * session options: lifetime, path, domain, secure, httponly were deprecated.
    Prefixed versions should now be used instead: cookie_lifetime, cookie_path, cookie_domain, cookie_secure, cookie_httponly

  Before:

  ```
    framework:
        session:
            lifetime:   3600
            path:       \
            domain:     example.com
            secure:     true
            httponly:   true
  ```

  After:

  ```
    framework:
        session:
            cookie_lifetime:   3600
            cookie_path:       \
            cookie_domain:     example.com
            cookie_secure:     true
            cookie_httponly:   true
  ```

Added `handler_id`, defaults to `session.handler.native_file`.

  ```
     framework:
         session:
             storage_id: session.storage.native
             handler_id: session.handler.native_file
  ```

To use mock session storage use the following.  `handler_id` is irrelevant in this context.

  ```
     framework:
         session:
             storage_id: session.storage.mock_file
  ```

### WebProfilerBundle

  * You must clear old profiles after upgrading to 2.1. If you are using a
    database then you will need to remove the table.
