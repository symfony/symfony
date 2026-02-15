CHANGELOG
=========

8.1
---

 * Add reverse class-map based on Map attribute
 * Merge nested properties when targeting the same class
 * Add a `targetClass` option to `MapCollection`
 * Add a `TransformObjectMapperAwareInterface` to inject the current object mapper instance to transformers

7.4
---

 * The component is not marked as `@experimental` anymore
 * Add `ObjectMapperAwareInterface` to set the owning object mapper instance
 * Add a `MapCollection` transform that calls the Mapper over iterable properties

7.3
---

 * Add the component as experimental
