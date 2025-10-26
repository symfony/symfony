CHANGELOG
=========

7.4
---

 * The component is not marked as `@experimental` anymore
 * Add `ObjectMapperAwareInterface` to set the owning object mapper instance
 * Add a `MapCollection` transform that calls the Mapper over iterable properties
 * Add a `DepthAwareInterface` to gather information about the mapping depth
 * Add a `TransformAllProperties` that applies a transform on all properties of
   a mapped object
* Add a `UninitializeProxy` transform that skips proxy initialization for a
  given depth

7.3
---

 * Add the component as experimental
