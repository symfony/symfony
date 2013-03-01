CHANGELOG
=========

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
