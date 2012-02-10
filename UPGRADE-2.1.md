UPGRADE FROM 2.0 to 2.1
=======================

* assets_base_urls and base_urls merging strategy has changed

    Unlike most configuration blocks, successive values for
    ``assets_base_urls`` will overwrite each other instead of being merged.
    This behavior was chosen because developers will typically define base
    URL's for each environment. Given that most projects tend to inherit
    configurations (e.g. ``config_test.yml`` imports ``config_dev.yml``)
    and/or share a common base configuration (i.e. ``config.yml``), merging
    could yield a set of base URL's for multiple environments.

* moved management of the locale from the Session class to the Request class

    Configuring the default locale:

    Before:

        framework:
          session:
              default_locale: fr

    After:

        framework:
          default_locale: fr

    Retrieving the locale from a Twig template:

    Before: `{{ app.request.session.locale }}` or `{{ app.session.locale }}`  
    After: `{{ app.request.locale }}`

    Retrieving the locale from a PHP template:

    Before: `$view['session']->getLocale()`  
    After: `$view['request']->getLocale()`

    Retrieving the locale from PHP code:

    Before: `$session->getLocale()`  
    After: `$request->getLocale()`

* Method `equals` of `Symfony\Component\Security\Core\User\UserInterface` has
  moved to `Symfony\Component\Security\Core\User\EquatableInterface`.

    You have to change the name of the `equals` function in your implementation
    of the `User` class to `isEqualTo` and implement `EquatableInterface`.
    Apart from that, no other changes are required to make it behave as before.
    Alternatively, you can use the default implementation provided
    by `AbstractToken:hasUserChanged` if you do not need any custom comparison logic.
    In this case do not implement the interface and remove your comparison function.

    Before:

        class User implements UserInterface
        {
            // ...
            public function equals(UserInterface $user) { /* ... */ }
            // ...
        }

    After:

        class User implements UserInterface, EquatableInterface
        {
            // ...
            public function isEqualTo(UserInterface $user) { /* ... */ }
            // ...
        }

* Form children aren't automatically validated anymore. That means that you
  explicitely need to set the `Valid` constraint in your model if you want to
  validate associated objects.

    If you don't want to set the `Valid` constraint, or if there is no reference
    from the data of the parent form to the data of the child form, you can
    enable BC behaviour by setting the option "cascade_validation" to `true` on
    the parent form.

* Changed implementation of choice lists

    ArrayChoiceList was replaced. If you have custom classes that extend
    this class, you can now extend SimpleChoiceList.

    Before:

        class MyChoiceList extends ArrayChoiceList
        {
            protected function load()
            {
                parent::load();

                // load choices

                $this->choices = $choices;
            }
        }

    After:

        class MyChoiceList extends SimpleChoiceList
        {
            public function __construct()
            {
                // load choices

                parent::__construct($choices);
            }
        }

    If you need to load the choices lazily - that is, as soon as they are
    accessed for the first time -  you can extend LazyChoiceList instead.

        class MyChoiceList extends LazyChoiceList
        {
            protected function loadChoiceList()
            {
                // load choices

                return new SimpleChoiceList($choices);
            }
        }

    PaddedChoiceList, MonthChoiceList and TimezoneChoiceList were removed.
    Their functionality was merged into DateType, TimeType and
    TimezoneType.

    EntityChoiceList was adapted. The methods `getEntities`,
    `getEntitiesByKeys`, `getIdentifier` and `getIdentifierValues` were
    removed/made private. Instead of the first two, you can now use
    `getChoices` and `getChoicesByValues`. For the latter two, no
    replacement exists.

* The strategy for generating the HTML attributes "id" and "name"
  of choices in a choice field has changed
  
    Instead of appending the choice value, a generated integer is now appended
    by default. Take care if your Javascript relies on that. If you can
    guarantee that your choice values only contain ASCII letters, digits,
    letters, colons and underscores, you can restore the old behaviour by
    setting the option "index_strategy" of the choice field to
    `ChoiceList::COPY_CHOICE`.

* The strategy for generating the HTML attributes "value" of choices in a
  choice field has changed
  
    Instead of using the choice value, a generated integer is now stored.
    Again, take care if your Javascript reads this value. If your choice field
    is a non-expanded single-choice field, or if the choices are guaranteed not
    to contain the empty string '' (which is the case when you added it manually
    or when the field is a single-choice field and is not required), you can
    restore the old behaviour by setting the option "value_strategy" to
    `ChoiceList::COPY_CHOICE`.

* In the template of the choice type, the structure of the "choices" variable
  has changed

    "choices" now contains ChoiceView objects with two getters `getValue`
    and `getLabel` to access the choice data. The indices of the array
    store an index whose generation is controlled by the "index_generation"
    option of the choice field.

    Before:

        {% for choice, label in choices %}
            <option value="{{ choice }}"{% if _form_is_choice_selected(form, choice) %} selected="selected"{% endif %}>
                {{ label }}
            </option>
        {% endfor %}

    After:

        {% for choice in choices %}
            <option value="{{ choice.value }}"{% if _form_is_choice_selected(form, choice) %} selected="selected"{% endif %}>
                {{ choice.label }}
            </option>
        {% endfor %}

* In the template of the collection type, the default name of the prototype
  field has changed from "$$name$$" to "__name__"

    For custom names, no dollars are prepended/appended anymore. You are advised
    to prepend and append double underscores wherever you have configured the
    prototype name manually.

    Before:

        $builder->add('tags', 'collection', array('prototype' => 'proto'));

        // results in the name "$$proto$$" in the template

    After:

        $builder->add('tags', 'collection', array('prototype' => '__proto__'));

        // results in the name "__proto__" in the template

* The methods `setMessage`, `getMessageTemplate` and `getMessageParameters`
  in Constraint were deprecated

    If you have implemented custom validators, you should use either of the
    `addViolation*` methods of the context object instead.

    Before:

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

    After:

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

* The options passed to `getParent` of the form types don't contain default
  options anymore

    You should check with `isset` if options exist before checking their value.

    Before:

        public function getParent(array $options)
        {
            return 'single_text' === $options['widget'] ? 'text' : 'choice';
        }

    After:

        public function getParent(array $options)
        {
            return isset($options['widget']) && 'single_text' === $options['widget'] ? 'text' : 'choice';
        }

* The methods `add`, `remove`, `setParent`, `bind` and `setData` in class Form
  now throw an exception if the form is already bound

    If you used these methods on bound forms, you should consider moving your
    logic to an event listener listening to either of the events
    FormEvents::PRE_BIND, FormEvents::BIND_CLIENT_DATA or
    FormEvents::BIND_NORM_DATA instead. 
