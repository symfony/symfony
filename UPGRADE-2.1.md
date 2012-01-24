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

    "choices" now contains ChoiceView objects with two getters `getValue()`
    and `getLabel()` to access the choice data. The indices of the array
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
