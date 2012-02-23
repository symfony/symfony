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

### Form and Validator

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

  * The strategy for generating the `id` and `name` HTML attributes for choices
    in a choice field has changed.

    Instead of appending the choice value, a generated integer is now appended
    by default. Take care if your JavaScript relies on the old behavior. If you
    can guarantee that your choice values only contain ASCII letters, digits,
    colons and underscores, you can restore the old behavior by setting the
    `index_strategy` choice field option to `ChoiceList::COPY_CHOICE`.

  * The strategy for generating the `value` HTML attribute for choices in a
    choice field has changed.

    Instead of using the choice value, a generated integer is now stored. Again,
    take care if your JavaScript reads this value. If your choice field is a
    non-expanded single-choice field, or if the choices are guaranteed not to
    contain the empty string '' (which is the case when you added it manually
    or when the field is a single-choice field and is not required), you can
    restore the old behavior by setting the `value_strategy` choice field option
    to `ChoiceList::COPY_CHOICE`.

  * In the choice field type's template, the structure of the `choices` variable
    has changed.

    The `choices` variable now contains ChoiceView objects with two getters,
    `getValue()` and `getLabel()`, to access the choice data. The indices of the
    array are controlled by the choice field's `index_generation` option.

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

  * The methods `setMessage()`, `getMessageTemplate()` and
    `getMessageParameters()` in the Constraint class were deprecated.

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

  * The options passed to the `getParent()` method of form types no longer
    contain default options.

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

  * The `add()`, `remove()`, `setParent()`, `bind()` and `setData()` methods in
    the Form class now throw an exception if the form is already bound.

    If you used these methods on bound forms, you should consider moving your
    logic to an event listener that observes one of the following events:
    `FormEvents::PRE_BIND`, `FormEvents::BIND_CLIENT_DATA` or
    `FormEvents::BIND_NORM_DATA`. 

### Session

  * Flash messages now return an array based on their type. The old method is
    still available but is now deprecated.

    ##### Retrieving the flash messages from a Twig template

    Before:

    ```
    {% if app.session.hasFlash('notice') %}
        <div class="flash-notice">
            {{ app.session.flash('notice') }}
        </div>
    {% endif %}
    ```

    After:

    ```
    {% if app.session.flashbag.has('notice') %}
        <div class="flash-notice">
            {{ app.session.flashbag.get('notice') }}
        </div>
    {% endif %}
    ```

    You can process all flash messges in a single loop with:

    ```
    {% for type, flashMessage in app.session.flashbag.all() %}
        <div class="flash-{{ type }}">
            {{ flashMessage }}
        </div>
    {% endforeach %}
    ```

  * Session storage drivers should inherit from
    `Symfony\Component\HttpFoundation\Session\Storage\AbstractSessionStorage`
    and should no longer implement `read()`, `write()`, and `remove()`, which
    were removed from `SessionStorageInterface`.

    Any session storage driver that wants to use custom save handlers should
    implement `Symfony\Component\HttpFoundation\Session\Storage\SessionHandlerInterface`.
