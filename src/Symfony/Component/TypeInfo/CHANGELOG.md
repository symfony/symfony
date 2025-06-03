CHANGELOG
=========

7.3
---

 * Add `Type::accepts()` method
 * Add the `TypeFactoryTrait::fromValue()`, `TypeFactoryTrait::arrayShape()`, and `TypeFactoryTrait::arrayKey()` methods
 * Deprecate constructing a `CollectionType` instance as a list that is not an array
 * Deprecate the third `$asList` argument of `TypeFactoryTrait::iterable()`, use `TypeFactoryTrait::list()` instead
 * Add type alias support in `TypeContext` and `StringTypeResolver`
 * Add `CollectionType::mergeCollectionValueTypes()` method
 * Add `ArrayShapeType` to represent the exact shape of an array
 * Add `Type::traverse()` method

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
