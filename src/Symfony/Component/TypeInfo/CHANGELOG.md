CHANGELOG
=========

7.2
---

 * Add construction validation for `BackedEnumType`, `CollectionType`, `GenericType`, `IntersectionType`, and `UnionType`
 * Add `TypeIdentifier::isStandalone()`, `TypeIdentifier::isScalar()`, and `TypeIdentifier::isBool()` methods
 * Add `WrappingTypeInterface` and `CompositeTypeInterface` type interfaces
 * Add `NullableType` type class
 * Rename `Type::isA()` to `Type::isIdentifiedBy()` and `Type::is()` to `Type::isSatisfiedBy()`
 * Remove `Type::getBaseType()`, `Type::asNonNullable()` and `Type::__call()` methods
 * Remove `CompositeTypeTrait`
 * Add `PhpDocAwareReflectionTypeResolver` resolver

7.1
---

 * Add the component as experimental
