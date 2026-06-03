CHANGELOG
=========

8.1
---

 * Add `DateTimeZone` value object support with `DateTimeZoneValueObjectTransformer`
 * Add `DateInterval` value object support with `DateIntervalValueObjectTransformer`
 * Add timezone support to `DateTimeValueObjectTransformer`
 * Add value object support
 * Deprecate `DateTimeTypePropertyMetadataLoader` (both Read and Write), date times are handled as value objects
 * Deprecate `DateTimeToStringValueTransformer` and `StringToDateTimeValueTransformer`, use `DateTimeValueObjectTransformer` instead
 * Deprecate `ValueTransformerInterface`, use `PropertyValueTransformerInterface` instead
 * Add `$defaultOptions` to `JsonStreamReader` and `JsonStreamWriter`
 * Reject JSON inputs reaching `Lexer::MAX_DEPTH` consistently with `json_decode()`

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
