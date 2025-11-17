CHANGELOG
=========

8.0
---

 * Remove `$streamToNativeValueTransformers` from `PropertyMetadata`

7.4
---

 * The component is not marked as `@experimental` anymore
 * Remove `nikic/php-parser` dependency
 * Add `_current_object` to the context passed to value transformers during write operations
 * Add `include_null_properties` option to encode the properties with `null` value
 * Add synthetic properties support
 * Deprecate `PropertyMetadata::$streamToNativeValueTransformers`, use `PropertyMetadata::$valueTransformers` instead
 * Deprecate `PropertyMetadata::getNativeToStreamValueTransformer()` and `PropertyMetadata::getStreamToNativeValueTransformers()`, use `PropertyMetadata::getValueTransformers()` instead
 * Deprecate `PropertyMetadata::withNativeToStreamValueTransformers()` and `PropertyMetadata::withStreamToNativeValueTransformers()`, use `PropertyMetadata::withValueTransformers()` instead
 * Deprecate `PropertyMetadata::withAdditionalNativeToStreamValueTransformer()` and `PropertyMetadata::withAdditionalStreamToNativeValueTransformer`, use `PropertyMetadata::withAdditionalValueTransformer()` instead

7.3
---

 * Introduce the component as experimental
