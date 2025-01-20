CHANGELOG
=========

7.3
---

 * Add `Type::accepts()` method
 * Add `TypeFactoryTrait::fromValue()` method

7.2
---

 * Add construction validation for `BackedEnumType`, `CollectionType`, `GenericType`, `IntersectionType`, and `UnionType`
 * Add `TypeIdentifier::isStandalone()`, `TypeIdentifier::isScalar()`, and `TypeIdentifier::isBool()` methods
 * Add `WrappingTypeInterface` and `CompositeTypeInterface` type interfaces
 * Add `NullableType` type class
 * Rename `Type::isA()` to `Type::isIdentifiedBy()` and `Type::is()` to `Type::isSatisfiedBy()`
 * Remove `Type::__call()`
 * Remove `Type::getBaseType()`, use `WrappingTypeInterface::getWrappedType()` instead
 * Remove `Type::asNonNullable()`, use `NullableType::getWrappedType()` instead
 * Remove `CompositeTypeTrait`
 * Add `PhpDocAwareReflectionTypeResolver` resolver
 * The type resolvers are not marked as `@internal` anymore
 * The component is not marked as `@experimental` anymore

7.1
---

 * Add the component as experimental
