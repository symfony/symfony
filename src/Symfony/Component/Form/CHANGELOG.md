CHANGELOG
=========

7.1
---

 * Add option `separator` to `ChoiceType` to use a custom separator after preferred choices (use the new `separator_html` option to display the separator text as HTML)
 * Deprecate not configuring the `default_protocol` option of the `UrlType`, it will default to `null` in 8.0 (the current default is `'http'`)
 * Add a `keep_as_list` option to `CollectionType`
 * Add an `input` option to `MoneyType`, to be able to cast the transformed value to `integer`

7.0
---

 * Throw when using `DateTime` or `DateTimeImmutable` model data with a different timezone than configured with the
   `model_timezone` option in `DateType`, `DateTimeType`, and `TimeType`
 * Make the "widget" option of date/time form types default to "single_text"
 * Require explicit argument when calling `Button/Form::setParent()`, `ButtonBuilder/FormConfigBuilder::setDataMapper()`, `TransformationFailedException::setInvalidMessage()`

6.4
---

 * Deprecate using `DateTime` or `DateTimeImmutable` model data with a different timezone than configured with the
   `model_timezone` option in `DateType`, `DateTimeType`, and `TimeType`
 * Deprecate `PostSetDataEvent::setData()`, use `PreSetDataEvent::setData()` instead
 * Deprecate `PostSubmitEvent::setData()`, use `PreSubmitDataEvent::setData()` or `SubmitDataEvent::setData()` instead
 * Add `duplicate_preferred_choices` option in `ChoiceType`
 * Add `$duplicatePreferredChoices` parameter to `ChoiceListFactoryInterface::createView()`

6.3
---

 * Don't render seconds for HTML5 date pickers unless "with_seconds" is explicitly set
 * Add a `placeholder_attr` option to `ChoiceType`
 * Deprecate not configuring the "widget" option of date/time form types, it will default to "single_text" in v7

6.2
---

 * Allow passing `TranslatableInterface` objects to the `ChoiceView` label
 * Allow passing `TranslatableInterface` objects to the `help` option
 * Deprecate calling `Button/Form::setParent()`, `ButtonBuilder/FormConfigBuilder::setDataMapper()`, `TransformationFailedException::setInvalidMessage()` without arguments
 * Change the signature of `FormConfigBuilderInterface::setDataMapper()` to `setDataMapper(?DataMapperInterface)`
 * Change the signature of `FormInterface::setParent()` to `setParent(?self)`
 * Add `PasswordHasherExtension` with support for `hash_property_path` option in `PasswordType`

6.1
---

 * Add a `prototype_options` option to `CollectionType`

6.0
---

 * Remove `PropertyPathMaper`
 * Remove `Symfony\Component\Form\Extension\Validator\Util\ServerParams`
 * Remove `FormPass` configuration
 * Remove the `NumberToLocalizedStringTransformer::ROUND_*` constants, use `\NumberFormatter::ROUND_*` instead
 * The `rounding_mode` option of the `PercentType` defaults to `\NumberFormatter::ROUND_HALFUP`
 * The rounding mode argument of the constructor of `PercentToLocalizedStringTransformer` defaults to `\NumberFormatter::ROUND_HALFUP`
 * Add `FormConfigInterface::getIsEmptyCallback()` and `FormConfigBuilderInterface::setIsEmptyCallback()`
 * Change `$forms` parameter type of the `DataMapper::mapDataToForms()` method from `iterable` to `\Traversable`
 * Change `$forms` parameter type of the `DataMapper::mapFormsToData()` method from `iterable` to `\Traversable`
 * Change `$checkboxes` parameter type of the `CheckboxListMapper::mapDataToForms()` method from `iterable` to `\Traversable`
 * Change `$checkboxes` parameter type of the `CheckboxListMapper::mapFormsToData()` method from `iterable` to `\Traversable`
 * Change `$radios` parameter type of the `RadioListMapper::mapDataToForms()` method from `iterable` to `\Traversable`
 * Change `$radios` parameter type of the `RadioListMapper::mapFormsToData()` method from `iterable` to `\Traversable`

5.4
---

 * Deprecate calling `FormErrorIterator::children()` if the current element is not iterable.
 * Allow to pass `TranslatableMessage` objects to the `help` option
 * Add the `EnumType`

5.3
---

 * Changed `$forms` parameter type of the `DataMapperInterface::mapDataToForms()` method from `iterable` to `\Traversable`.
 * Changed `$forms` parameter type of the `DataMapperInterface::mapFormsToData()` method from `iterable` to `\Traversable`.
 * Deprecated passing an array as the second argument of the `DataMapper::mapDataToForms()` method, pass `\Traversable` instead.
 * Deprecated passing an array as the first argument of the `DataMapper::mapFormsToData()` method, pass `\Traversable` instead.
 * Deprecated passing an array as the second argument of the `CheckboxListMapper::mapDataToForms()` method, pass `\Traversable` instead.
 * Deprecated passing an array as the first argument of the `CheckboxListMapper::mapFormsToData()` method, pass `\Traversable` instead.
 * Deprecated passing an array as the second argument of the `RadioListMapper::mapDataToForms()` method, pass `\Traversable` instead.
 * Deprecated passing an array as the first argument of the `RadioListMapper::mapFormsToData()` method, pass `\Traversable` instead.
 * Added a `choice_translation_parameters` option to `ChoiceType`
 * Add `UuidType` and `UlidType`
 * Dependency on `symfony/intl` was removed. Install `symfony/intl` if you are using `LocaleType`, `CountryType`, `CurrencyType`, `LanguageType` or `TimezoneType`.
 * Add `priority` option to `BaseType` and sorting view fields

5.2.0
-----

 * Added support for using the `{{ label }}` placeholder in constraint messages, which is replaced in the `ViolationMapper` by the corresponding field form label.
 * Added `DataMapper`, `ChainAccessor`, `PropertyPathAccessor` and `CallbackAccessor` with new callable `getter` and `setter` options for each form type
 * Deprecated `PropertyPathMapper` in favor of `DataMapper` and `PropertyPathAccessor`
 * Added an `html5` option to `MoneyType` and `PercentType`, to use `<input type="number" />`

5.1.0
-----

 * Deprecated not configuring the `rounding_mode` option of the `PercentType`. It will default to `\NumberFormatter::ROUND_HALFUP` in Symfony 6.
 * Deprecated not passing a rounding mode to the constructor of `PercentToLocalizedStringTransformer`. It will default to `\NumberFormatter::ROUND_HALFUP` in Symfony 6.
 * Added `collection_entry` block prefix to `CollectionType` entries
 * Added a `choice_filter` option to `ChoiceType`
 * Added argument `callable|null $filter` to `ChoiceListFactoryInterface::createListFromChoices()` and `createListFromLoader()` - not defining them is deprecated.
 * Added a `ChoiceList` facade to leverage explicit choice list caching based on options
 * Added an `AbstractChoiceLoader` to simplify implementations and handle global optimizations
 * The `view_timezone` option defaults to the `model_timezone` if no `reference_date` is configured.
 * Implementing the `FormConfigInterface` without implementing the `getIsEmptyCallback()` method
   is deprecated. The method will be added to the interface in 6.0.
 * Implementing the `FormConfigBuilderInterface` without implementing the `setIsEmptyCallback()` method
   is deprecated. The method will be added to the interface in 6.0.
 * Added a `rounding_mode` option for the PercentType and correctly round the value when submitted
 * Deprecated `Symfony\Component\Form\Extension\Validator\Util\ServerParams` in favor of its parent class `Symfony\Component\Form\Util\ServerParams`
 * Added the `html5` option to the `ColorType` to validate the input
 * Deprecated `NumberToLocalizedStringTransformer::ROUND_*` constants, use `\NumberFormatter::ROUND_*` instead

5.0.0
-----

 * Removed support for using different values for the "model_timezone" and "view_timezone" options of the `TimeType`
   without configuring a reference date.
 * Removed the `scale` option of the `IntegerType`.
 * Using the `date_format`, `date_widget`, and `time_widget` options of the `DateTimeType` when the `widget` option is
   set to `single_text` is not supported anymore.
 * The `format` option of `DateType` and `DateTimeType` cannot be used when the `html5` option is enabled.
 * Using names for buttons that do not start with a letter, a digit, or an underscore throw an exception
 * Using names for buttons that do not contain only letters, digits, underscores, hyphens, and colons throw an exception.
 * removed the `ChoiceLoaderInterface` implementation in `CountryType`, `LanguageType`, `LocaleType` and `CurrencyType`
 * removed `getExtendedType()` method of the `FormTypeExtensionInterface`
 * added static `getExtendedTypes()` method to the `FormTypeExtensionInterface`
 * calling to `FormRenderer::searchAndRenderBlock()` method for fields which were already rendered throw a `BadMethodCallException`
 * removed the `regions` option of the `TimezoneType`
 * removed the `$scale` argument of the `IntegerToLocalizedStringTransformer`
 * removed `TemplatingExtension` and `TemplatingRendererEngine` classes, use Twig instead
 * passing a null message when instantiating a `Symfony\Component\Form\FormError` is not allowed
 * removed support for using `int` or `float` as data for the `NumberType` when the `input` option is set to `string`

4.4.0
-----

 * add new `WeekType`
 * using different values for the "model_timezone" and "view_timezone" options of the `TimeType` without configuring a
   reference date is deprecated
 * preferred choices are repeated in the list of all choices
 * deprecated using `int` or `float` as data for the `NumberType` when the `input` option is set to `string`
 * The type guesser guesses the HTML accept attribute when a mime type is configured in the File or Image constraint.
 * Overriding the methods `FormIntegrationTestCase::setUp()`, `TypeTestCase::setUp()` and `TypeTestCase::tearDown()` without the `void` return-type is deprecated.
 * marked all dispatched event classes as `@final`
 * Added the `validate` option to `SubmitType` to toggle the browser built-in form validation.
 * Added the `alpha3` option to `LanguageType` and `CountryType` to use alpha3 instead of alpha2 codes

4.3.0
-----

 * added a `symbol` option to the `PercentType` that allows to disable or customize the output of the percent character
 * Using the `format` option of `DateType` and `DateTimeType` when the `html5` option is enabled is deprecated.
 * Using names for buttons that do not start with a letter, a digit, or an underscore is deprecated and will lead to an
   exception in 5.0.
 * Using names for buttons that do not contain only letters, digits, underscores, hyphens, and colons is deprecated and
   will lead to an exception in 5.0.
 * added `html5` option to `NumberType` that allows to render `type="number"` input fields
 * deprecated using the `date_format`, `date_widget`, and `time_widget` options of the `DateTimeType` when the `widget`
   option is set to `single_text`
 * added `block_prefix` option to `BaseType`.
 * added `help_html` option to display the `help` text as HTML.
 * `FormError` doesn't implement `Serializable` anymore
 * `FormDataCollector` has been marked as `final`
 * added `label_translation_parameters`, `attr_translation_parameters`, `help_translation_parameters` options
   to `FormType` to pass translation parameters to form labels, attributes (`placeholder` and `title`) and help text respectively.
   The passed parameters will replace placeholders in translation messages.

   ```php
   class OrderType extends AbstractType
   {
       public function buildForm(FormBuilderInterface $builder, array $options)
       {
           $builder->add('comment', TextType::class, [
               'label' => 'Comment to the order to %company%',
               'label_translation_parameters' => [
                   '%company%' => 'Acme',
               ],
               'help' => 'The address of the %company% is %address%',
               'help_translation_parameters' => [
                   '%company%' => 'Acme Ltd.',
                   '%address%' => '4 Form street, Symfonyville',
               ],
           ])
       }
   }
   ```
 * added the `input_format` option to `DateType`, `DateTimeType`, and `TimeType` to specify the input format when setting
   the `input` option to `string`
 * dispatch `PreSubmitEvent` on `form.pre_submit`
 * dispatch `SubmitEvent` on `form.submit`
 * dispatch `PostSubmitEvent` on `form.post_submit`
 * dispatch `PreSetDataEvent` on `form.pre_set_data`
 * dispatch `PostSetDataEvent` on `form.post_set_data`
 * added an `input` option to `NumberType`
 * removed default option grouping in `TimezoneType`, use `group_by` instead

4.2.0
-----

 * The `getExtendedType()` method of the `FormTypeExtensionInterface` is deprecated and will be removed in 5.0. Type
   extensions must implement the static `getExtendedTypes()` method instead and return an iterable of extended types.

   Before:

   ```php
   class FooTypeExtension extends AbstractTypeExtension
   {
       public function getExtendedType()
       {
           return FormType::class;
       }

       // ...
   }
   ```

   After:

   ```php
   class FooTypeExtension extends AbstractTypeExtension
   {
       public static function getExtendedTypes(): iterable
       {
           return [FormType::class];
       }

       // ...
   }
   ```
 * deprecated the `$scale` argument of the `IntegerToLocalizedStringTransformer`
 * added `Symfony\Component\Form\ClearableErrorsInterface`
 * deprecated calling `FormRenderer::searchAndRenderBlock` for fields which were already rendered
 * added a cause when a CSRF error has occurred
 * deprecated the `scale` option of the `IntegerType`
 * removed restriction on allowed HTTP methods
 * deprecated the `regions` option of the `TimezoneType`

4.1.0
-----

 * added `input=datetime_immutable` to `DateType`, `TimeType`, `DateTimeType`
 * added `rounding_mode` option to `MoneyType`
 * added `choice_translation_locale` option to `CountryType`, `LanguageType`, `LocaleType` and `CurrencyType`
 * deprecated the `ChoiceLoaderInterface` implementation in `CountryType`, `LanguageType`, `LocaleType` and `CurrencyType`
 * added `input=datetime_immutable` to DateType, TimeType, DateTimeType
 * added `rounding_mode` option to MoneyType

4.0.0
-----

 * using the `choices` option in `CountryType`, `CurrencyType`, `LanguageType`,
   `LocaleType`, and `TimezoneType` when the `choice_loader` option is not `null`
   is not supported anymore and the configured choices will be ignored
 * callable strings that are passed to the options of the `ChoiceType` are
   treated as property paths
 * the `choices_as_values` option of the `ChoiceType` has been removed
 * removed the support for caching loaded choice lists in `LazyChoiceList`,
   cache the choice list in the used `ChoiceLoaderInterface` implementation
   instead
 * removed the support for objects implementing both `\Traversable` and `\ArrayAccess` in `ResizeFormListener::preSubmit()`
 * removed the ability to use `FormDataCollector` without the `symfony/var-dumper` component
 * removed passing a `ValueExporter` instance to the `FormDataExtractor::__construct()` method
 * removed passing guesser services ids as the fourth argument of `DependencyInjectionExtension::__construct()`
 * removed the ability to validate an unsubmitted form.
 * removed `ChoiceLoaderInterface` implementation in `TimezoneType`
 * added the `false_values` option to the `CheckboxType` which allows to configure custom values which will be treated as `false` during submission

3.4.0
-----

 * added `DebugCommand`
 * deprecated `ChoiceLoaderInterface` implementation in `TimezoneType`
 * added options "input" and "regions" to `TimezoneType`
 * added an option to ``Symfony\Component\Form\FormRendererEngineInterface::setTheme()`` and
   ``Symfony\Component\Form\FormRendererInterface::setTheme()`` to disable usage of default themes when rendering a form

3.3.0
-----

 * deprecated using "choices" option in ``CountryType``, ``CurrencyType``, ``LanguageType``, ``LocaleType``, and
   ``TimezoneType`` when "choice_loader" is not ``null``
 * added `Symfony\Component\Form\FormErrorIterator::findByCodes()`
 * added `getTypedExtensions`, `getTypes`, and `getTypeGuessers` to `Symfony\Component\Form\Test\FormIntegrationTestCase`
 * added `FormPass`

3.2.0
-----

 * added `CallbackChoiceLoader`
 * implemented `ChoiceLoaderInterface` in children of `ChoiceType`

3.1.0
-----

 * deprecated the "choices_as_values" option of ChoiceType
 * deprecated support for data objects that implements both `Traversable` and
   `ArrayAccess` in `ResizeFormListener::preSubmit` method
 * Using callable strings as choice options in `ChoiceType` has been deprecated
   and will be used as `PropertyPath` instead of callable in Symfony 4.0.
 * implemented `DataTransformerInterface` in `TextType`
 * deprecated caching loaded choice list in `LazyChoiceList::$loadedList`

3.0.0
-----

 * removed `FormTypeInterface::setDefaultOptions()` method
 * removed `AbstractType::setDefaultOptions()` method
 * removed `FormTypeExtensionInterface::setDefaultOptions()` method
 * removed `AbstractTypeExtension::setDefaultOptions()` method
 * added `FormTypeInterface::configureOptions()` method
 * added `FormTypeExtensionInterface::configureOptions()` method

2.8.0
-----

 * added option "choice_translation_domain" to DateType, TimeType and DateTimeType.
 * deprecated option "read_only" in favor of "attr['readonly']"
 * added the html5 "range" FormType
 * deprecated the "cascade_validation" option in favor of setting "constraints"
   with the Valid constraint
 * moved data trimming logic of TrimListener into StringUtil
 * [BC BREAK] When registering a type extension through the DI extension, the tag alias has to match the actual extended type.

2.7.38
------

 * [BC BREAK] the `isFileUpload()` method was added to the `RequestHandlerInterface`

2.7.0
-----

 * added option "choice_translation_domain" to ChoiceType.
 * deprecated option "precision" in favor of "scale"
 * deprecated the overwriting of AbstractType::setDefaultOptions() in favor of overwriting AbstractType::configureOptions().
 * deprecated the overwriting of AbstractTypeExtension::setDefaultOptions() in favor of overwriting AbstractTypeExtension::configureOptions().
 * added new ChoiceList interface and implementations in the Symfony\Component\Form\ChoiceList namespace
 * added new ChoiceView in the Symfony\Component\Form\ChoiceList\View namespace
 * choice groups are now represented by ChoiceGroupView objects in the view
 * deprecated the old ChoiceList interface and implementations
 * deprecated the old ChoiceView class
 * added CheckboxListMapper and RadioListMapper
 * deprecated ChoiceToBooleanArrayTransformer and ChoicesToBooleanArrayTransformer
 * deprecated FixCheckboxInputListener and FixRadioInputListener
 * deprecated the "choice_list" option of ChoiceType
 * added new options to ChoiceType:
   * "choices_as_values"
   * "choice_loader"
   * "choice_label"
   * "choice_name"
   * "choice_value"
   * "choice_attr"
   * "group_by"

2.6.2
-----

 * Added back the `model_timezone` and `view_timezone` options for `TimeType`, `DateType`
   and `BirthdayType`

2.6.0
-----

 * added "html5" option to Date, Time and DateTimeFormType to be able to
   enable/disable HTML5 input date when widget option is "single_text"
 * added "label_format" option with possible placeholders "%name%" and "%id%"
 * [BC BREAK] drop support for model_timezone and view_timezone options in TimeType, DateType and BirthdayType,
   update to 2.6.2 to get back support for these options

2.5.0
------

 * deprecated options "max_length" and "pattern" in favor of putting these values in "attr" option
 * added an option for multiple files upload
 * form errors now reference their cause (constraint violation, exception, ...)
 * form errors now remember which form they were originally added to
 * [BC BREAK] added two optional parameters to FormInterface::getErrors() and
   changed the method to return a Symfony\Component\Form\FormErrorIterator
   instance instead of an array
 * errors mapped to unsubmitted forms are discarded now
 * ObjectChoiceList now compares choices by their value, if a value path is
   given
 * you can now pass interface names in the "data_class" option
 * [BC BREAK] added `FormInterface::getTransformationFailure()`

2.4.0
-----

 * moved CSRF implementation to the new Security CSRF sub-component
 * deprecated CsrfProviderInterface and its implementations
 * deprecated options "csrf_provider" and "intention" in favor of the new options "csrf_token_manager" and "csrf_token_id"

2.3.0
-----

 * deprecated FormPerformanceTestCase and FormIntegrationTestCase in the Symfony\Component\Form\Tests namespace and moved them to the Symfony\Component\Form\Test namespace
 * deprecated TypeTestCase in the Symfony\Component\Form\Tests\Extension\Core\Type namespace and moved it to the Symfony\Component\Form\Test namespace
 * changed FormRenderer::humanize() to humanize also camel cased field name
 * added RequestHandlerInterface and FormInterface::handleRequest()
 * deprecated passing a Request instance to FormInterface::bind()
 * added options "method" and "action" to FormType
 * deprecated option "virtual" in favor "inherit_data"
 * deprecated VirtualFormAwareIterator in favor of InheritDataAwareIterator
 * [BC BREAK] removed the "array" type hint from DataMapperInterface
 * improved forms inheriting their parent data to actually return that data from getData(), getNormData() and getViewData()
 * added component-level exceptions for various SPL exceptions
   changed all uses of the deprecated Exception class to use more specialized exceptions instead
   removed NotInitializedException, NotValidException, TypeDefinitionException, TypeLoaderException, CreationException
 * added events PRE_SUBMIT, SUBMIT and POST_SUBMIT
 * deprecated events PRE_BIND, BIND and POST_BIND
 * [BC BREAK] renamed bind() and isBound() in FormInterface to submit() and isSubmitted()
 * added methods submit() and isSubmitted() to Form
 * deprecated bind() and isBound() in Form
 * deprecated AlreadyBoundException in favor of AlreadySubmittedException
 * added support for PATCH requests
 * [BC BREAK] added initialize() to FormInterface
 * [BC BREAK] added getAutoInitialize() to FormConfigInterface
 * [BC BREAK] added setAutoInitialize() to FormConfigBuilderInterface
 * [BC BREAK] initialization for Form instances added to a form tree must be manually disabled
 * PRE_SET_DATA is now guaranteed to be called after children were added by the form builder,
   unless FormInterface::setData() is called manually
 * fixed CSRF error message to be translated
 * custom CSRF error messages can now be set through the "csrf_message" option
 * fixed: expanded single-choice fields now show a radio button for the empty value

2.2.0
-----

 * TrimListener now removes unicode whitespaces
 * deprecated getParent(), setParent() and hasParent() in FormBuilderInterface
 * FormInterface::add() now accepts a FormInterface instance OR a field's name, type and options
 * removed special characters between the choice or text fields of DateType unless
   the option "format" is set to a custom value
 * deprecated FormException and introduced ExceptionInterface instead
 * [BC BREAK] FormException is now an interface
 * protected FormBuilder methods from being called when it is turned into a FormConfigInterface with getFormConfig()
 * [BC BREAK] inserted argument `$message` in the constructor of `FormError`
 * the PropertyPath class and related classes were moved to a dedicated
   PropertyAccess component. During the move, InvalidPropertyException was
   renamed to NoSuchPropertyException. FormUtil was split: FormUtil::singularify()
   can now be found in Symfony\Component\PropertyAccess\StringUtil. The methods
   getValue() and setValue() from PropertyPath were extracted into a new class
   PropertyAccessor.
 * added an optional PropertyAccessorInterface parameter to FormType,
   ObjectChoiceList and PropertyPathMapper
 * [BC BREAK] PropertyPathMapper and FormType now have a constructor
 * [BC BREAK] setting the option "validation_groups" to ``false`` now disables validation
   instead of assuming group "Default"

2.1.0
-----

 * [BC BREAK] ``read_only`` field attribute now renders as ``readonly="readonly"``, use ``disabled`` instead
 * [BC BREAK] child forms now aren't validated anymore by default
 * made validation of form children configurable (new option: cascade_validation)
 * added support for validation groups as callbacks
 * made the translation catalogue configurable via the "translation_domain" option
 * added Form::getErrorsAsString() to help debugging forms
 * allowed setting different options for RepeatedType fields (like the label)
 * added support for empty form name at root level, this enables rendering forms
   without form name prefix in field names
 * [BC BREAK] form and field names must start with a letter, digit or underscore
   and only contain letters, digits, underscores, hyphens and colons
 * [BC BREAK] changed default name of the prototype in the "collection" type
   from "$$name$$" to "\__name\__". No dollars are appended/prepended to custom
   names anymore.
 * [BC BREAK] improved ChoiceListInterface
 * [BC BREAK] added SimpleChoiceList and LazyChoiceList as replacement of
   ArrayChoiceList
 * added ChoiceList and ObjectChoiceList to use objects as choices
 * [BC BREAK] removed EntitiesToArrayTransformer and EntityToIdTransformer.
   The former has been replaced by CollectionToArrayTransformer in combination
   with EntityChoiceList, the latter is not required in the core anymore.
 * [BC BREAK] renamed
   * ArrayToBooleanChoicesTransformer to ChoicesToBooleanArrayTransformer
   * ScalarToBooleanChoicesTransformer to ChoiceToBooleanArrayTransformer
   * ArrayToChoicesTransformer to ChoicesToValuesTransformer
   * ScalarToChoiceTransformer to ChoiceToValueTransformer
   to be consistent with the naming in ChoiceListInterface.
   They were merged into ChoiceList and have no public equivalent anymore.
 * choice fields now throw a FormException if neither the "choices" nor the
   "choice_list" option is set
 * the radio type is now a child of the checkbox type
 * the collection, choice (with multiple selection) and entity (with multiple
   selection) types now make use of addXxx() and removeXxx() methods in your
   model if you set "by_reference" to false. For a custom, non-recognized
   singular form, set the "property_path" option like this: "plural|singular"
 * forms now don't create an empty object anymore if they are completely
   empty and not required. The empty value for such forms is null.
 * added constant Guess::VERY_HIGH_CONFIDENCE
 * [BC BREAK] The methods `add`, `remove`, `setParent`, `bind` and `setData`
   in class Form now throw an exception if the form is already bound
 * fields of constrained classes without a NotBlank or NotNull constraint are
   set to not required now, as stated in the docs
 * fixed TimeType and DateTimeType to not display seconds when "widget" is
   "single_text" unless "with_seconds" is set to true
 * checkboxes of in an expanded multiple-choice field don't include the choice
   in their name anymore. Their names terminate with "[]" now.
 * deprecated FormValidatorInterface and substituted its implementations
   by event subscribers
 * simplified CSRF protection and removed the csrf type
 * deprecated FieldType and merged it into FormType
 * added new option "compound" that lets you switch between field and form behavior
 * [BC BREAK] renamed theme blocks
   * "field_*" to "form_*"
   * "field_widget" to "form_widget_simple"
   * "widget_choice_options" to "choice_widget_options"
   * "generic_label" to "form_label"
 * added theme blocks "form_widget_compound", "choice_widget_expanded" and
   "choice_widget_collapsed" to make theming more modular
 * ValidatorTypeGuesser now guesses "collection" for array type constraint
 * added method `guessPattern` to FormTypeGuesserInterface to guess which pattern to use in the HTML5 attribute "pattern"
 * deprecated method `guessMinLength` in favor of `guessPattern`
 * labels don't display field attributes anymore. Label attributes can be
   passed in the "label_attr" option/variable
 * added option "mapped" which should be used instead of setting "property_path" to false
 * [BC BREAK] "data_class" now *must* be set if a form maps to an object and should be left empty otherwise
 * improved error mapping on forms
   * dot (".") rules are now allowed to map errors assigned to a form to
     one of its children
 * errors are not mapped to unsynchronized forms anymore
 * [BC BREAK] changed Form constructor to accept a single `FormConfigInterface` object
 * [BC BREAK] changed argument order in the FormBuilder constructor
 * added Form method `getViewData`
 * deprecated Form methods
   * `getTypes`
   * `getErrorBubbling`
   * `getNormTransformers`
   * `getClientTransformers`
   * `getAttribute`
   * `hasAttribute`
   * `getClientData`
 * added FormBuilder methods
   * `getTypes`
   * `addViewTransformer`
   * `getViewTransformers`
   * `resetViewTransformers`
   * `addModelTransformer`
   * `getModelTransformers`
   * `resetModelTransformers`
 * deprecated FormBuilder methods
   * `prependClientTransformer`
   * `appendClientTransformer`
   * `getClientTransformers`
   * `resetClientTransformers`
   * `prependNormTransformer`
   * `appendNormTransformer`
   * `getNormTransformers`
   * `resetNormTransformers`
 * deprecated the option "validation_constraint" in favor of the new
   option "constraints"
 * removed superfluous methods from DataMapperInterface
   * `mapFormToData`
   * `mapDataToForm`
 * added `setDefaultOptions` to FormTypeInterface and FormTypeExtensionInterface
   which accepts an OptionsResolverInterface instance
 * deprecated the methods `getDefaultOptions` and `getAllowedOptionValues`
   in FormTypeInterface and FormTypeExtensionInterface
 * options passed during construction can now be accessed from FormConfigInterface
 * added FormBuilderInterface and FormConfigEditorInterface
 * [BC BREAK] the method `buildForm` in FormTypeInterface and FormTypeExtensionInterface
   now receives a FormBuilderInterface instead of a FormBuilder instance
 * [BC BREAK] the method `buildViewBottomUp` was renamed to `finishView` in
   FormTypeInterface and FormTypeExtensionInterface
 * [BC BREAK] the options array is now passed as last argument of the
   methods
   * `buildView`
   * `finishView`
   in FormTypeInterface and FormTypeExtensionInterface
 * [BC BREAK] no options are passed to `getParent` of FormTypeInterface anymore
 * deprecated DataEvent and FilterDataEvent in favor of the new FormEvent which is
   now passed to all events thrown by the component
 * FormEvents::BIND now replaces FormEvents::BIND_NORM_DATA
 * FormEvents::PRE_SET_DATA now replaces FormEvents::SET_DATA
 * FormEvents::PRE_BIND now replaces FormEvents::BIND_CLIENT_DATA
 * deprecated FormEvents::SET_DATA, FormEvents::BIND_CLIENT_DATA and
   FormEvents::BIND_NORM_DATA
 * [BC BREAK] reversed the order of the first two arguments to `createNamed`
   and `createNamedBuilder` in `FormFactoryInterface`
 * deprecated `getChildren` in Form and FormBuilder in favor of `all`
 * deprecated `hasChildren` in Form and FormBuilder in favor of `count`
 * FormBuilder now implements \IteratorAggregate
 * [BC BREAK] compound forms now always need a data mapper
 * FormBuilder now maintains the order when explicitly adding form builders as children
 * ChoiceType now doesn't add the empty value anymore if the choices already contain an empty element
 * DateType, TimeType and DateTimeType now show empty values again if not required
 * [BC BREAK] fixed rendering of errors for DateType, BirthdayType and similar ones
 * [BC BREAK] fixed: form constraints are only validated if they belong to the validated group
 * deprecated `bindRequest` in `Form` and replaced it by a listener to FormEvents::PRE_BIND
 * fixed: the "data" option supersedes default values from the model
 * changed DateType to refer to the "format" option for calculating the year and day choices instead
   of padding them automatically
 * [BC BREAK] DateType defaults to the format "yyyy-MM-dd" now if the widget is
   "single_text", in order to support the HTML 5 date field out of the box
 * added the option "format" to DateTimeType
 * [BC BREAK] DateTimeType now outputs RFC 3339 dates by default, as generated and
   consumed by HTML5 browsers, if the widget is "single_text"
 * deprecated the options "data_timezone" and "user_timezone" in DateType, DateTimeType and TimeType
   and renamed them to "model_timezone" and "view_timezone"
 * fixed: TransformationFailedExceptions thrown in the model transformer are now caught by the form
 * added FormRegistryInterface, ResolvedFormTypeInterface and ResolvedFormTypeFactoryInterface
 * deprecated FormFactory methods
   * `addType`
   * `hasType`
   * `getType`
 * [BC BREAK] FormFactory now expects a FormRegistryInterface and a ResolvedFormTypeFactoryInterface as constructor argument
 * [BC BREAK] The method `createBuilder` in FormTypeInterface is not supported anymore for performance reasons
 * [BC BREAK] Removed `setTypes` from FormBuilder
 * deprecated AbstractType methods
   * `getExtensions`
   * `setExtensions`
 * ChoiceType now caches its created choice lists to improve performance
 * [BC BREAK] Rows of a collection field cannot be themed individually anymore. All rows in the collection
   field now have the same block names, which contains "entry" where it previously contained the row index.
 * [BC BREAK] When registering a type through the DI extension, the tag alias has to match the actual type name.
 * added FormRendererInterface, FormRendererEngineInterface and implementations of these interfaces
 * [BC BREAK] removed the following methods from FormUtil:
   * `toArrayKey`
   * `toArrayKeys`
   * `isChoiceGroup`
   * `isChoiceSelected`
 * [BC BREAK] renamed method `renderBlock` in FormHelper to `block` and changed its signature
 * made FormView properties public and deprecated their accessor methods
 * made the normalized data of a form accessible in the template through the variable "form.vars.data"
 * made the original data of a choice accessible in the template through the property "choice.data"
 * added convenience class Forms and FormFactoryBuilderInterface
