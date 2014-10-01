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

  * The priorities for the built-in listeners have changed.

    ```
                                            2.0         2.1
        security.firewall   kernel.request  64          8
        locale listener     kernel.request  0           16
        router listener     early_request   255         n/a
                            request         0           32
   ```

### Doctrine

  * The DoctrineBundle is moved from the Symfony repository to the Doctrine repository.
    Therefore you should change the namespace of this bundle in your AppKernel.php:

    Before: `new Symfony\Bundle\DoctrineBundle\DoctrineBundle()`

    After: `new Doctrine\Bundle\DoctrineBundle\DoctrineBundle()`

### HttpFoundation

  * Locale management was moved from the Session class to the Request class.

    ##### Configuring the default locale

    Before:

    ```yaml
    framework:
        session:
            default_locale: fr
    ```

    After:

    ```yaml
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

    ##### Simulate old behavior

    You can simulate that the locale for the user is still stored in the session by
    registering a listener that looks like the following if the parameter which
    handles the locale value in the request is `_locale`:

   ```php
   namespace XXX;

   use Symfony\Component\HttpKernel\Event\GetResponseEvent;
   use Symfony\Component\HttpKernel\KernelEvents;
   use Symfony\Component\EventDispatcher\EventSubscriberInterface;

   class LocaleListener implements EventSubscriberInterface
   {
       private $defaultLocale;

       public function __construct($defaultLocale = 'en')
       {
           $this->defaultLocale = $defaultLocale;
       }

       public function onKernelRequest(GetResponseEvent $event)
       {
           $request = $event->getRequest();
           if (!$request->hasPreviousSession()) {
               return;
           }

           if ($locale = $request->attributes->get('_locale')) {
               $request->getSession()->set('_locale', $locale);
           } else {
               $request->setLocale($request->getSession()->get('_locale', $this->defaultLocale));
           }
       }

       static public function getSubscribedEvents()
       {
           return array(
               // must be registered before the default Locale listener
               KernelEvents::REQUEST => array(array('onKernelRequest', 17)),
           );
       }
   }
   ```

  * The methods `getPathInfo()`, `getBaseUrl()` and `getBasePath()` of
    a `Request` now all return a raw value (vs a urldecoded value before). Any call
    to one of these methods must be checked and wrapped in a `rawurldecode()` if
    needed.

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

    ```php
    class User implements UserInterface
    {
        // ...
        public function equals(UserInterface $user) { /* ... */ }
        // ...
    }
    ```

    After:

    ```php
    class User implements UserInterface, EquatableInterface
    {
        // ...
        public function isEqualTo(UserInterface $user) { /* ... */ }
        // ...
    }
    ```

  * The custom factories for the firewall configuration are now
    registered during the build method of bundles instead of being registered
    by the end-user. This means that you will need to remove the 'factories'
    keys in your security configuration.

    Before:

     ```yaml
     security:
       factories:
         - "%kernel.root_dir%/../src/Acme/DemoBundle/Resources/config/security_factories.yml"
     ```

     ```yaml
     # src/Acme/DemoBundle/Resources/config/security_factories.yml
     services:
         security.authentication.factory.custom:
             class:  Acme\DemoBundle\DependencyInjection\Security\Factory\CustomFactory
             tags:
                 - { name: security.listener.factory }
     ```

     After:

      ```php
      namespace Acme\DemoBundle;

      use Symfony\Component\HttpKernel\Bundle\Bundle;
      use Symfony\Component\DependencyInjection\ContainerBuilder;
      use Acme\DemoBundle\DependencyInjection\Security\Factory\CustomFactory;

      class AcmeDemoBundle extends Bundle
      {
          public function build(ContainerBuilder $container)
          {
              parent::build($container);

              $extension = $container->getExtension('security');
              $extension->addSecurityListenerFactory(new CustomFactory());
          }
      }
      ```

  * The Firewall listener is now registered after the Router listener. This
    means that specific Firewall URLs (like /login_check and /logout) must now
    have proper routes defined in your routing configuration. Also, if you have
    a custom 404 error page, make sure that you do not use any security related
    features such as `is_granted` on it.

  * The user provider configuration has been refactored. The configuration
    for the chain provider and the memory provider has been changed:

     Before:

     ```yaml
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

     ```yaml
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

  * The `UserPassword` constraint has moved from the Security Bundle to the Security Component:

     Before:

     ```php
     use Symfony\Bundle\SecurityBundle\Validator\Constraint\UserPassword;
     use Symfony\Bundle\SecurityBundle\Validator\Constraint as SecurityAssert;
     ```

     After:

     ```php
     use Symfony\Component\Security\Core\Validator\Constraint\UserPassword;
     use Symfony\Component\Security\Core\Validator\Constraint as SecurityAssert;
     ```

### Form

#### BC Breaks in Form Types and Options

  * A third argument `$options` was added to the methods `buildView()` and
    `buildViewBottomUp()` in `FormTypeInterface` and `FormTypeExtensionInterface`.
    Furthermore, `buildViewBottomUp()` was renamed to `finishView()`. At last,
    all methods in these types now receive instances of `FormBuilderInterface`
    where they received instances of `FormBuilder` before. You need to change the
    method signatures in your form types and extensions as shown below.

    Before:

    ```php
    use Symfony\Component\Form\FormBuilder;

    public function buildForm(FormBuilder $builder, array $options)
    ```

    After:

    ```php
    use Symfony\Component\Form\FormBuilderInterface;

    public function buildForm(FormBuilderInterface $builder, array $options)
    ```

  * The method `createBuilder` was removed from `FormTypeInterface` for performance
    reasons. It is now not possible anymore to use custom implementations of
    `FormBuilderInterface` for specific form types.

    If you are in such a situation, you can implement a custom `ResolvedFormTypeInterface`
    where you create your own `FormBuilderInterface` implementation. You also need to
    register a custom `ResolvedFormTypeFactoryInterface` implementation under the service
    name "form.resolved_type_factory" in order to replace the default implementation.

  * If you previously inherited from `FieldType`, you should now inherit from
    `FormType`. You should also set the option `compound` to `false` if your field
    is not supposed to contain child fields.

    `FieldType` was deprecated and will be removed in Symfony 2.3.

    Before:

    ```php
    public function getParent(array $options)
    {
        return 'field';
    }
    ```

    After:

    ```php
    public function getParent()
    {
        return 'form';
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'compound' => false,
        ));
    }
    ```

    The changed signature of `getParent()` is explained in the next step.
    The new method `setDefaultOptions` is described in the section "Deprecations".

  * No options are passed to `getParent()` of `FormTypeInterface` anymore. If
    you previously dynamically inherited from `FormType` or `FieldType`, you can now
    dynamically set the "compound" option instead.

    Before:

    ```php
    public function getParent(array $options)
    {
        return $options['expanded'] ? 'form' : 'field';
    }
    ```

    After:

    ```php
    use Symfony\Component\OptionsResolver\OptionsResolverInterface;
    use Symfony\Component\OptionsResolver\Options;

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $compound = function (Options $options) {
            return $options['expanded'];
        };

        $resolver->setDefaults(array(
            'compound' => $compound,
        ));
    }

    public function getParent()
    {
        return 'form';
    }
    ```

    The new method `setDefaultOptions` is described in the section "Deprecations".

  * The "data_class" option now *must* be set if a form maps to an object. If
    you leave it empty, the form will expect an array, an instance of \ArrayAccess
    or a scalar value and fail with a corresponding exception.

    Likewise, if a form maps to an array or an instance of \ArrayAccess, the option
    *must* be left null now.

    Form mapped to an instance of `Person`:

    ```php
    use Symfony\Component\OptionsResolver\OptionsResolverInterface;

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Acme\Demo\Person',
        ));
    }
    ```

  * The mapping of property paths to arrays has changed.

    Previously, a property path "street" mapped to both a field `$street` of
    a class (or its accessors `getStreet()` and `setStreet()`) and an index
    `['street']` of an array or an object implementing `\ArrayAccess`.

    Now, the property path "street" only maps to a class field (or accessors),
    while the property path "[street]" only maps to indices.

    If you defined property paths manually in the "property_path" option, you
    should revise them and adjust them if necessary.

    Before:

    ```php
    $builder->add('name', 'text', array(
        'property_path' => 'address.street',
    ));
    ```

    After (if the address object is an array):

    ```php
    $builder->add('name', 'text', array(
        'property_path' => 'address[street]',
    ));
    ```

    If address is an object in this case, the code given in "Before"
    works without changes.

  * Form and field names must now start with a letter, digit or underscore
    and only contain letters, digits, underscores, hyphens and colons.

  * In the collection type's template, the default name of the prototype field
    has changed from `$$name$$` to `__name__`.

    You can now customize the name of the prototype field by changin the
    "prototype_name" option. You are advised to prepend and append two
    underscores wherever you specify a value for the field's "prototype_name"
    option.

    ```php
    $builder->add('tags', 'collection', array('prototype_name' => '__proto__'));

    // results in the name "__proto__" in the template
    ```

  * The "read_only" option now renders as `readonly="readonly"`, use
    "disabled" instead for `disabled="disabled"`.

  * Child forms are no longer automatically validated. That means that you must
    explicitly set the `Valid` constraint in your model if you want to validate
    objects modified by child forms.

    If you don't want to set the `Valid` constraint, or if there is no reference
    from the data of the parent form to the data of the child form, you can
    enable BC behavior by setting the "cascade_validation" option to `true`
    on the parent form.

#### BC Breaks in Themes and HTML

  * FormType and FieldType were merged and require you to adapt your form
    themes.

    The block `field_widget` and all references to it should be renamed to
    `form_widget_simple`:

    Before:

    ```jinja
    {% block url_widget %}
    {% spaceless %}
        {% set type = type|default('url') %}
        {{ block('field_widget') }}
    {% endspaceless %}
    {% endblock url_widget %}
    ```

    After:

    ```jinja
    {% block url_widget %}
    {% spaceless %}
        {% set type = type|default('url') %}
        {{ block('form_widget_simple') }}
    {% endspaceless %}
    {% endblock url_widget %}
    ```

    All other `field_*` blocks and references to them should be renamed to
    `form_*`. If you previously defined both a `field_*` and a `form_*`
    block, you can merge them into a single `form_*` block and check the new
    Boolean variable `compound` instead:

    Before:

    ```jinja
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

    ```jinja
    {% block form_errors %}
    {% spaceless %}
        {% if compound %}
            ... form code ...
        {% else %}
            ... field code ...
        {% endif %}
    {% endspaceless %}
    {% endblock form_errors %}
    ```

    Furthermore, the block `generic_label` was merged into `form_label`. You
    should now override `form_label` in order to customize labels.

    Last but not least, the block `widget_choice_options` was renamed to
    `choice_widget_options` to be consistent with the rest of the default
    theme.

  * The strategy for generating the `id` and `name` HTML attributes for
    checkboxes and radio buttons in a choice field has changed.

    Instead of appending the choice value, a generated integer is now appended
    by default. Take care if your JavaScript relies on that. If you want to
    read the actual choice value, read the `value` attribute instead.

  * In the choice field type's template, the `_form_is_choice_selected` method
    used to identify a selected choice has been replaced with the `selectedchoice`
    filter. Similarly, the `_form_is_choice_group` method used to check if a
    choice is grouped has been removed and can be checked with the `iterable`
    test.

    Before:

    ```jinja
    {% for choice, label in choices %}
        {% if _form_is_choice_group(label) %}
            <optgroup label="{{ choice|trans }}">
                {% for nestedChoice, nestedLabel in label %}
                    ... options tags ...
                {% endfor %}
            </optgroup>
        {% else %}
            <option value="{{ choice }}"{% if _form_is_choice_selected(form, choice) %} selected="selected"{% endif %}>
                {{ label }}
            </option>
        {% endif %}
    {% endfor %}
    ```

    After:

    ```jinja
    {% for label, choice in choices %}
        {% if choice is iterable %}
            <optgroup label="{{ label|trans({}, translation_domain) }}">
                {% for nestedChoice, nestedLabel in choice %}
                    ... options tags ...
                {% endfor %}
            </optgroup>
        {% else %}
            <option value="{{ choice.value }}"{% if choice is selectedchoice(value) %} selected="selected"{% endif %}>
                {{ label }}
            </option>
        {% endif %}
    {% endfor %}
    ```

  * Creation of default labels has been moved to the view layer. You will need
    to incorporate this logic into any custom `form_label` templates to
    accommodate those cases when the `label` option has not been explicitly
    set.

    ```jinja
    {% block form_label %}
        {% if label is empty %}
            {% set label = name|humanize %}
        {% endif %}

        {# ... #}

    {% endblock %}
    ````

  * Custom styling of individual rows of a collection form has been removed for
    performance reasons. Instead, all rows now have the same block name, where
    the word "entry" replaces the previous occurrence of the row index.

    Before:

    ```jinja
    {% block _author_tags_0_label %}
        {# ... #}
    {% endblock %}

    {% block _author_tags_1_label %}
        {# ... #}
    {% endblock %}
    ```

    After:

    ```jinja
    {% block _author_tags_entry_label %}
        {# ... #}
    {% endblock %}
    ```

  * The method `renderBlock()` of the helper for the PHP Templating component was
    renamed to `block()`. Its first argument is now expected to be a `FormView`
    instance.

    Before:

    ```php
    <?php echo $view['form']->renderBlock('widget_attributes') ?>
    ```

    After:

    ```php
    <?php echo $view['form']->block($form, 'widget_attributes') ?>
    ```

#### Other BC Breaks

  * The order of the first two arguments of the methods `createNamed` and
    `createNamedBuilder` in `FormFactoryInterface` was reversed to be
    consistent with the rest of the component. You should scan your code
    for occurrences of these methods and reverse the parameters.

    Before:

    ```php
    $form = $factory->createNamed('text', 'firstName');
    ```

    After:

    ```php
    $form = $factory->createNamed('firstName', 'text');
    ```

  * The implementation of `ChoiceList` was changed heavily. As a result,
    `ArrayChoiceList` was replaced. If you have custom classes that extend
    this class, you must now extend `SimpleChoiceList` and pass choices
    to the parent constructor.

    Before:

    ```php
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

    ```php
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
    accessed for the first time -- you can extend `LazyChoiceList` instead
    and load the choices by overriding `loadChoiceList()`.

    ```php
    class MyChoiceList extends LazyChoiceList
    {
        protected function loadChoiceList()
        {
            // load choices

            return new SimpleChoiceList($choices);
        }
    }
    ```

    `PaddedChoiceList`, `MonthChoiceList` and `TimezoneChoiceList` were removed.
    Their functionality was merged into `DateType`, `TimeType` and `TimezoneType`.

    `EntityChoiceList` was adapted. The methods `getEntities()`,
    `getEntitiesByKeys()`, `getIdentifier()` and `getIdentifierValues()` were
    removed or made private. Instead of the first two, you can now use
    `getChoices()` and `getChoicesByValues()`. For the latter two, no
    replacement exists.

  * HTML attributes are now passed in the `label_attr` variable for the `form_label` function.

    Before:

    ```jinja
    {{ form_label(form.name, 'Your Name', { 'attr': {'class': 'foo'} }) }}
    ```

    After:

    ```jinja
    {{ form_label(form.name, 'Your Name', { 'label_attr': {'class': 'foo'} }) }}
    ```

  * `EntitiesToArrayTransformer` and `EntityToIdTransformer` were removed.
    The former was replaced by `CollectionToArrayTransformer` in combination
    with `EntityChoiceList`, the latter is not required in the core anymore.

  * The following transformers were renamed:

      * `ArrayToBooleanChoicesTransformer` to `ChoicesToBooleanArrayTransformer`
      * `ScalarToBooleanChoicesTransformer` to `ChoiceToBooleanArrayTransformer`
      * `ArrayToChoicesTransformer` to `ChoicesToValuesTransformer`
      * `ScalarToChoiceTransformer` to `ChoiceToValueTransformer`

    to be consistent with the naming in `ChoiceListInterface`.

  * `FormUtil::toArrayKey()` and `FormUtil::toArrayKeys()` were removed.
    They were merged into ChoiceList and have no public equivalent anymore.

  * The `add()`, `remove()`, `setParent()`, `bind()` and `setData()` methods in
    the Form class now throw an exception if the form is already bound.

    If you used these methods on bound forms, you should consider moving your
    logic to an event listener that observes `FormEvents::PRE_BIND` or
    `FormEvents::BIND`.

#### Deprecations

  * The following methods of `FormTypeInterface` and `FormTypeExtensionInterface`
    are deprecated and will be removed in Symfony 2.3:

      * `getDefaultOptions`
      * `getAllowedOptionValues`

    You should use the newly added `setDefaultOptions` instead, which gives you
    access to the OptionsResolverInterface instance and with that a lot more power.

    Before:

    ```php
    public function getDefaultOptions(array $options)
    {
        return array(
            'gender' => 'male',
        );
    }

    public function getAllowedOptionValues(array $options)
    {
        return array(
            'gender' => array('male', 'female'),
        );
    }
    ```

    After:

    ```php
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'gender' => 'male',
        ));

        $resolver->setAllowedValues(array(
            'gender' => array('male', 'female'),
        ));
    }
    ```

    You can specify options that depend on other options using closures.

    Before:

    ```php
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

    ```php
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'empty_data' => function (Options $options, $value) {
                return $options['multiple'] ? array() : $value;
            }
        ));
    }
    ```

    The second argument `$value` contains the current default value and
    does not have to be specified if not needed.

  * The following methods in `FormBuilder` were deprecated and have a new
    equivalent:

      * `prependClientTransformer`: `addViewTransformer`, with `true` as second argument
      * `appendClientTransformer`: `addViewTransformer`
      * `getClientTransformers`: `getViewTransformers`
      * `resetClientTransformers`: `resetViewTransformers`
      * `prependNormTransformer`: `addModelTransformer`
      * `appendNormTransformer`: `addModelTransformer`, with `true` as second argument
      * `getNormTransformers`: `getModelTransformers`
      * `resetNormTransformers`: `resetModelTransformers`

    The deprecated methods will be removed in Symfony 2.3. You are advised to
    update your application.

    Before:

    ```php
    $builder->appendClientTransformer(new MyTransformer());
    ```

    After:

    ```php
    $builder->addViewTransformer(new MyTransformer());
    ```

  * The following events were deprecated and have a new equivalent:

      * `FormEvents::SET_DATA`: `FormEvents::PRE_SET_DATA`
      * `FormEvents::BIND_CLIENT_DATA`: `FormEvents::PRE_BIND`
      * `FormEvents::BIND_NORM_DATA`: `FormEvents::BIND`

    The deprecated events will be removed in Symfony 2.3.

    Furthermore, the event classes `DataEvent` and `FilterDataEvent` were
    deprecated and replaced by the generic `FormEvent`. You are advised to
    code your listeners against the new event now. The deprecated events will
    be removed in Symfony 2.3.

    Before:

    ```php
    $builder->addListener(FormEvents::BIND_CLIENT_DATA, function (FilterDataEvent $event) {
        // ...
    });
    ```

    After:

    ```php
    $builder->addListener(FormEvents::PRE_BIND, function (FormEvent $event) {
        // ...
    });
    ```

  * The interface `FormValidatorInterface` was deprecated and will be removed
    in Symfony 2.3.

    If you implemented custom validators using this interface, you can
    substitute them by event listeners listening to the `FormEvents::POST_BIND`
    (or any other of the `*BIND` events). In case you used the CallbackValidator
    class, you should now pass the callback directly to `addEventListener`.

  * The method `guessMinLength()` of `FormTypeGuesserInterface` was deprecated
    and will be removed in Symfony 2.3. You should use the new method
    `guessPattern()` instead which may return any regular expression that
    is inserted in the HTML5 attribute `pattern`.

    Before:

    ```php
    public function guessMinLength($class, $property)
    {
        if (/* condition */) {
            return new ValueGuess($minLength, Guess::LOW_CONFIDENCE);
        }
    }
    ```

    After:

    ```php
    public function guessPattern($class, $property)
    {
        if (/* condition */) {
            return new ValueGuess('.{'.$minLength.',}', Guess::LOW_CONFIDENCE);
        }
    }
    ```

  * Setting the option "property_path" to `false` was deprecated and will be unsupported
    as of Symfony 2.3.

    You should use the new option "mapped" instead in order to set that you don't want
    a field to be mapped to its parent's data.

    Before:

    ```php
    $builder->add('termsAccepted', 'checkbox', array(
        'property_path' => false,
    ));
    ```

    After:

    ```php
    $builder->add('termsAccepted', 'checkbox', array(
        'mapped' => false,
    ));
    ```

  * The following methods in `Form` were deprecated and will be removed in
    Symfony 2.3:

      * `getTypes`
      * `getErrorBubbling`
      * `getNormTransformers`
      * `getClientTransformers`
      * `getAttribute`
      * `hasAttribute`
      * `getClientData`
      * `getChildren`
      * `hasChildren`
      * `bindRequest`

    Before:

    ```php
    $form->getErrorBubbling()
    ```

    After:

    ```php
    $form->getConfig()->getErrorBubbling();
    ```

    The method `getClientData` has a new equivalent that is named `getViewData`.
    You can access all other methods on the `FormConfigInterface` object instead.

    Instead of `getChildren` and `hasChildren`, you should now use `all` and
    `count`.

    Before:

    ```php
    if ($form->hasChildren()) {
    ```

    After:

    ```php
    if (count($form) > 0) {
    ```

    Instead of `bindRequest`, you should now simply call `bind`:

    Before:

    ```php
    $form->bindRequest($request);
    ```

    After:

    ```php
    $form->bind($request);
    ```

  * The option "validation_constraint" was deprecated and will be removed
    in Symfony 2.3. You should use the option "constraints" instead,
    where you can pass one or more constraints for a form.

    Before:

    ```php
    $builder->add('name', 'text', array(
        'validation_constraint' => new NotBlank(),
    ));
    ```

    After:

    ```php
    $builder->add('name', 'text', array(
        'constraints' => new NotBlank(),
    ));
    ```

    Unlike previously, you can also pass a list of constraints now:

    ```php
    $builder->add('name', 'text', array(
        'constraints' => array(
            new NotBlank(),
            new MinLength(3),
        ),
    ));
    ```

    Be aware that constraints will now only be validated if they belong
    to the validated group! So if you validate a form in group "Custom"
    and previously did:

    ```php
    $builder->add('name', 'text', array(
        'validation_constraint' => new NotBlank(),
    ));
    ```

    Then you need to add the constraint to the group "Custom" now:

    ```php
    $builder->add('name', 'text', array(
        'constraints' => new NotBlank(array('groups' => 'Custom')),
    ));
    ```

  * The options "data_timezone" and "user_timezone" in `DateType`,
    `DateTimeType` and `TimeType` were deprecated and will be removed in
    Symfony 2.3. They were renamed to "model_timezone" and "view_timezone".

    Before:

    ```php
    $builder->add('scheduledFor', 'date', array(
        'data_timezone' => 'UTC',
        'user_timezone' => 'America/New_York',
    ));
    ```

    After:

    ```php
    $builder->add('scheduledFor', 'date', array(
        'model_timezone' => 'UTC',
        'view_timezone' => 'America/New_York',
    ));
    ```

  * The methods `addType`, `hasType` and `getType` in `FormFactory` are deprecated
    and will be removed in Symfony 2.3. You should use the methods with the same
    name on the `FormRegistry` instead.

    Before:

    ```php
    $this->get('form.factory')->addType(new MyFormType());
    ```

    After:

    ```php
    $registry = $this->get('form.registry');

    $registry->addType($registry->resolveType(new MyFormType()));
    ```

  * The following methods in class `FormView` were deprecated and will be
    removed in Symfony 2.3:

      * `set`
      * `has`
      * `get`
      * `all`
      * `getVars`
      * `addChild`
      * `getChild`
      * `getChildren`
      * `removeChild`
      * `hasChild`
      * `hasChildren`
      * `getParent`
      * `hasParent`
      * `setParent`

    You should access the public properties `vars`, `children` and `parent`
    instead.

    Before:

    ```php
    $view->set('help', 'A text longer than six characters');
    $view->set('error_class', 'max_length_error');
    ```

    After:

    ```php
    $view->vars = array_replace($view->vars, array(
        'help'        => 'A text longer than six characters',
        'error_class' => 'max_length_error',
    ));
    ```

    Before:

    ```php
    echo $view->get('error_class');
    ```

    After:

    ```php
    echo $view->vars['error_class'];
    ```

    Before:

    ```php
    if ($view->hasChildren()) { ...
    ```

    After:

    ```php
    if (count($view->children)) { ...
    ```

### Validator

  * The methods `setMessage()`, `getMessageTemplate()` and
    `getMessageParameters()` in the `ConstraintValidator` class were deprecated and will
    be removed in Symfony 2.3.

    If you have implemented custom validators, you should use the
    `addViolation()` method on the `ExecutionContext` object instead.

    Before:

    ```php
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

    ```php
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

    ```php
    public function isPropertyValid(ExecutionContext $context)
    {
        // ...
        $propertyPath = $context->getPropertyPath().'.property';
        $context->setPropertyPath($propertyPath);
        $context->addViolation('Error Message', array(), null);
    }
    ```

    After:

    ```php
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

    ```php
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

    ```php
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

  * Core translation messages changed. A dot is added at the end of each message.
    Overwritten core translations need to be fixed.

  * Collections (arrays or instances of `\Traversable`) in properties
    annotated with `Valid` are not traversed recursively by default anymore.

    This means that if a collection contains an entry which is again a
    collection, the inner collection won't be traversed anymore as it
    happened before. You can set the BC behavior by setting the new property
    `deep` of `Valid` to `true`.

    Before:

    ```php
    /** @Assert\Valid */
    private $recursiveCollection;
    ```

    After:

    ```php
    /** @Assert\Valid(deep = true) */
    private $recursiveCollection;
    ```

  * The `Size`, `Min` and `Max` constraints were deprecated and will be removed in
    Symfony 2.3. You should use the new constraint `Range` instead.

    Before:

    ```php
    /** @Assert\Size(min = 2, max = 16) */
    private $numberOfCpus;
    ```

    After:

    ```php
    /** @Assert\Range(min = 2, max = 16) */
    private $numberOfCpus;
    ```

    Before:

    ```php
    /** @Assert\Min(2) */
    private $numberOfCpus;
    ```

    After:

    ```php
    /** @Assert\Range(min = 2) */
    private $numberOfCpus;
    ```

  * The `MinLength` and `MaxLength` constraints were deprecated and will be
    removed in Symfony 2.3. You should use the new constraint `Length` instead.

    Before:

    ```php
    /** @Assert\MinLength(8) */
    private $password;
    ```

    After:

    ```php
    /** @Assert\Length(min = 8) */
    private $password;
    ```

  * The classes `ValidatorContext` and `ValidatorFactory` were deprecated and
    will be removed in Symfony 2.3. You should use the new entry point
    `Validation` instead.

    Before:

    ```php
    $validator = ValidatorFactory::buildDefault(array('path/to/mapping.xml'))
        ->getValidator();
    ```

    After:

    ```php
    $validator = Validation::createValidatorBuilder()
        ->addXmlMapping('path/to/mapping.xml')
        ->getValidator();
    ```

### Session

  * The namespace of the Session class changed from `Symfony\Component\HttpFoundation\Session`
    to `Symfony\Component\HttpFoundation\Session\Session`.

  * Using `get` to retrieve flash messages now returns an array.

    ##### Retrieving the flash messages from a Twig template

    Before:

    ```jinja
    {% if app.session.hasFlash('notice') %}
        <div class="flash-notice">
            {{ app.session.getFlash('notice') }}
        </div>
    {% endif %}
    ```
    After:

    ```jinja
    {% for flashMessage in app.session.flashbag.get('notice') %}
        <div class="flash-notice">
            {{ flashMessage }}
        </div>
    {% endfor %}
    ```

    You can process all flash messages in a single loop with:

    ```jinja
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
   all lowercased to camelCased (e.g. `mypropertyvalue` to `myPropertyValue`).

 * The `item` element is now converted to an array when deserializing XML.

    ```xml
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

  * Two new parameters have been added to the DIC: `router.request_context.host`
    and `router.request_context.scheme`.  You can customize them for your
    functional tests or for generating urls with the right host and scheme
    when your are in the cli context.

### FrameworkBundle

  * session options: lifetime, path, domain, secure, httponly were deprecated.
    Prefixed versions should now be used instead: cookie_lifetime, cookie_path, cookie_domain, cookie_secure, cookie_httponly

  Before:

  ```yaml
    framework:
        session:
            lifetime:   3600
            path:       \
            domain:     example.com
            secure:     true
            httponly:   true
  ```

  After:

  ```yaml
    framework:
        session:
            cookie_lifetime:   3600
            cookie_path:       \
            cookie_domain:     example.com
            cookie_secure:     true
            cookie_httponly:   true
  ```

Added `handler_id`, defaults to `session.handler.native_file`.

  ```yaml
     framework:
         session:
             storage_id: session.storage.native
             handler_id: session.handler.native_file
  ```

To use mock session storage use the following.  `handler_id` is irrelevant in this context.

  ```yaml
     framework:
         session:
             storage_id: session.storage.mock_file
  ```

### WebProfilerBundle

  * You must clear old profiles after upgrading to 2.1. If you are using a
    database then you will need to remove the table.
