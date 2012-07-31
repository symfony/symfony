CHANGELOG
=========

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
