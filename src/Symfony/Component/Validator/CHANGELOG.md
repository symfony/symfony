CHANGELOG
=========

2.4.0
-----

 * added a constraint the uses the expression language
 * added `minRatio`, `maxRatio`, `allowSquare`, `allowLandscape`, and `allowPortrait` to Image validator

2.3.0
-----

 * added the ISBN, ISSN, and IBAN validators
 * copied the constraints `Optional` and `Required` to the
   `Symfony\Component\Validator\Constraints\` namespace and deprecated the original
   classes.
 * added comparison validators (EqualTo, NotEqualTo, LessThan, LessThanOrEqualTo, GreaterThan, GreaterThanOrEqualTo, IdenticalTo, NotIdenticalTo)

2.2.0
-----

 * added a CardScheme validator
 * added a Luhn validator
 * moved @api-tags from `Validator` to `ValidatorInterface`
 * moved @api-tags from `ConstraintViolation` to the new `ConstraintViolationInterface`
 * moved @api-tags from `ConstraintViolationList` to the new `ConstraintViolationListInterface`
 * moved @api-tags from `ExecutionContext` to the new `ExecutionContextInterface`
 * [BC BREAK] `ConstraintValidatorInterface::initialize` is now type hinted against `ExecutionContextInterface` instead of `ExecutionContext`
 * [BC BREAK] changed the visibility of the properties in `Validator` from protected to private
 * deprecated `ClassMetadataFactoryInterface` in favor of the new `MetadataFactoryInterface`
 * deprecated `ClassMetadataFactory::getClassMetadata` in favor of `getMetadataFor`
 * created `MetadataInterface`, `PropertyMetadataInterface`, `ClassBasedInterface` and `PropertyMetadataContainerInterface`
 * deprecated `GraphWalker` in favor of the new `ValidationVisitorInterface`
 * deprecated `ExecutionContext::addViolationAtPath`
 * deprecated `ExecutionContext::addViolationAtSubPath` in favor of `ExecutionContextInterface::addViolationAt`
 * deprecated `ExecutionContext::getCurrentClass` in favor of `ExecutionContextInterface::getClassName`
 * deprecated `ExecutionContext::getCurrentProperty` in favor of `ExecutionContextInterface::getPropertyName`
 * deprecated `ExecutionContext::getCurrentValue` in favor of `ExecutionContextInterface::getValue`
 * deprecated `ExecutionContext::getGraphWalker` in favor of `ExecutionContextInterface::validate` and `ExecutionContextInterface::validateValue`
 * improved `ValidatorInterface::validateValue` to accept arrays of constraints
 * changed `ValidatorInterface::getMetadataFactory` to return a `MetadataFactoryInterface` instead of a `ClassMetadataFactoryInterface`
 * removed `ClassMetadataFactoryInterface` type hint from `ValidatorBuilderInterface::setMetadataFactory`.
   As of Symfony 2.3, this method will be typed against `MetadataFactoryInterface` instead.
 * [BC BREAK] the switches `traverse` and `deep` in the `Valid` constraint and in `GraphWalker::walkReference`
   are ignored for arrays now. Arrays are always traversed recursively.
 * added dependency to Translation component
 * violation messages are now translated with a TranslatorInterface implementation
 * [BC BREAK] inserted argument `$message` in the constructor of `ConstraintViolation`
 * [BC BREAK] inserted arguments `$translator` and `$translationDomain` in the constructor of `ExecutionContext`
 * [BC BREAK] inserted arguments `$translator` and `$translationDomain` in the constructor of `GraphWalker`
 * [BC BREAK] inserted arguments `$translator` and `$translationDomain` in the constructor of `ValidationVisitor`
 * [BC BREAK] inserted arguments `$translator` and `$translationDomain` in the constructor of `Validator`
 * [BC BREAK] added `setTranslator()` and `setTranslationDomain()` to `ValidatorBuilderInterface`
 * improved the Validator to support pluralized messages by default
 * [BC BREAK] changed the source of all pluralized messages in the translation files to the pluralized version
 * added ExceptionInterface, BadMethodCallException and InvalidArgumentException

2.1.0
-----

 * added support for `ctype_*` assertions in `TypeValidator`
 * improved the ImageValidator with min width, max width, min height, and max height constraints
 * added support for MIME with wildcard in FileValidator
 * changed Collection validator to add "missing" and "extra" errors to
   individual fields
 * changed default value for `extraFieldsMessage` and `missingFieldsMessage`
   in Collection constraint
 * made ExecutionContext immutable
 * deprecated Constraint methods `setMessage`, `getMessageTemplate` and
   `getMessageParameters`
 * added support for dynamic group sequences with the GroupSequenceProvider pattern
 * [BC BREAK] ConstraintValidatorInterface method `isValid` has been renamed to
   `validate`, its return value was dropped. ConstraintValidator still contains
   `isValid` for BC
 * [BC BREAK] collections in fields annotated with `Valid` are not traversed
   recursively anymore by default. `Valid` contains a new property `deep`
   which enables the BC behavior.
 * added Count constraint
 * added Length constraint
 * added Range constraint
 * deprecated the Min and Max constraints
 * deprecated the MinLength and MaxLength constraints
 * added Validation and ValidatorBuilderInterface
 * deprecated ValidatorContext, ValidatorContextInterface and ValidatorFactory
