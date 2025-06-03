CHANGELOG
=========

7.3
---

 * Deprecate the `CompiledClassMetadataFactory` and `CompiledClassMetadataCacheWarmer` classes
 * Register `NormalizerInterface` and `DenormalizerInterface` aliases for named serializers
 * Add `NumberNormalizer` to normalize `BcMath\Number` and `GMP` as `string`
 * Add `defaultType` to `DiscriminatorMap`

7.2
---

 * Deprecate the `csv_escape_char` context option of `CsvEncoder` and the `CsvEncoder::ESCAPE_CHAR_KEY` constant
 * Deprecate `CsvEncoderContextBuilder::withEscapeChar()` method
 * Add `SnakeCaseToCamelCaseNameConverter`
 * Support subclasses of `\DateTime` and `\DateTimeImmutable` for denormalization
 * Add the `UidNormalizer::NORMALIZATION_FORMAT_RFC9562` constant
 * Add support for configuring multiple serializer instances with different
   default contexts, name converters, sets of normalizers and encoders
 * Add support for collection profiles of multiple serializer instances
 * Deprecate `AdvancedNameConverterInterface`, use `NameConverterInterface` instead

7.1
---

 * Add arguments `$class`, `$format` and `$context` to `NameConverterInterface::normalize()` and `NameConverterInterface::denormalize()`
 * Add `DateTimeNormalizer::CAST_KEY` context option
 * Add `Default` and "class name" default groups
 * Add `AbstractNormalizer::FILTER_BOOL` context option
 * Add `CamelCaseToSnakeCaseNameConverter::REQUIRE_SNAKE_CASE_PROPERTIES` context option
 * Deprecate `AbstractNormalizerContextBuilder::withDefaultContructorArguments(?array $defaultContructorArguments)`, use `withDefaultConstructorArguments(?array $defaultConstructorArguments)` instead (note the missing `s` character in Contructor word in deprecated method)
 * Add `XmlEncoder::CDATA_WRAPPING_PATTERN` context option

7.0
---

 * Add method `getSupportedTypes()` to `DenormalizerInterface` and `NormalizerInterface`
 * Remove denormalization support for `AbstractUid` in `UidNormalizer`, use one of `AbstractUid` child class instead
 * Denormalizing to an abstract class in `UidNormalizer` now throws an `\Error`
 * Remove `ContextAwareDenormalizerInterface`, use `DenormalizerInterface` instead
 * Remove `ContextAwareNormalizerInterface`, use `NormalizerInterface` instead
 * Remove `CacheableSupportsMethodInterface`, use `NormalizerInterface` and `DenormalizerInterface` instead
 * Require explicit argument when calling `AttributeMetadata::setSerializedName()` and `ClassMetadata::setClassDiscriminatorMapping()`
 * Add argument `$context` to `NormalizerInterface::supportsNormalization()` and `DenormalizerInterface::supportsDenormalization()`
 * Remove Doctrine annotations support in favor of native attributes
 * Remove `AnnotationLoader`, use `AttributeLoader` instead

6.4
---

 * Add `TranslatableNormalizer`
 * Allow `Context` attribute to target classes
 * Deprecate Doctrine annotations support in favor of native attributes
 * Allow the `Groups` attribute/annotation on classes
 * JsonDecode: Add `json_decode_detailed_errors` option
 * Make `ProblemNormalizer` give details about Messenger's `ValidationFailedException`
 * Add `XmlEncoder::CDATA_WRAPPING` context option
 * Deprecate `AnnotationLoader`, use `AttributeLoader` instead
 * Add aliases for all classes in the `Annotation` namespace to `Attribute`

6.3
---

 * Add `AbstractNormalizer::REQUIRE_ALL_PROPERTIES` context flag to require all properties to be listed in the input instead of falling back to null for nullable ones
 * Add `XmlEncoder::SAVE_OPTIONS` context option
 * Add `BackedEnumNormalizer::ALLOW_INVALID_VALUES` context option
 * Add `UnsupportedFormatException` which is thrown when there is no decoder for a given format
 * Add method `getSupportedTypes(?string $format)` to `NormalizerInterface` and `DenormalizerInterface`
 * Make `ProblemNormalizer` give details about `ValidationFailedException` and `PartialDenormalizationException`
 * Deprecate `CacheableSupportsMethodInterface` in favor of the new `getSupportedTypes(?string $format)` methods
 * The following Normalizer classes will become final in 7.0:
   * `ConstraintViolationListNormalizer`
   * `CustomNormalizer`
   * `DataUriNormalizer`
   * `DateIntervalNormalizer`
   * `DateTimeNormalizer`
   * `DateTimeZoneNormalizer`
   * `GetSetMethodNormalizer`
   * `JsonSerializableNormalizer`
   * `ObjectNormalizer`
   * `PropertyNormalizer`

6.2
---

 * Add support for constructor promoted properties to `Context` attribute
 * Add context option `PropertyNormalizer::NORMALIZE_VISIBILITY` with bitmask flags `PropertyNormalizer::NORMALIZE_PUBLIC`, `PropertyNormalizer::NORMALIZE_PROTECTED`, `PropertyNormalizer::NORMALIZE_PRIVATE`
 * Add method `withNormalizeVisibility` to `PropertyNormalizerContextBuilder`
 * Deprecate calling `AttributeMetadata::setSerializedName()`, `ClassMetadata::setClassDiscriminatorMapping()` without arguments
 * Change the signature of `AttributeMetadataInterface::setSerializedName()` to `setSerializedName(?string)`
 * Change the signature of `ClassMetadataInterface::setClassDiscriminatorMapping()` to `setClassDiscriminatorMapping(?ClassDiscriminatorMapping)`
 * Add option YamlEncoder::YAML_INDENTATION to YamlEncoder constructor options to configure additional indentation for each level of nesting. This allows configuring indentation in the service configuration.
 * Add `SerializedPath` annotation to flatten nested attributes

6.1
---

 * Add `TraceableSerializer`, `TraceableNormalizer`, `TraceableEncoder` and `SerializerDataCollector` to integrate with the web profiler
 * Add the ability to create contexts using context builders
 * Set `Context` annotation as not final
 * Deprecate `ContextAwareNormalizerInterface`, use `NormalizerInterface` instead
 * Deprecate `ContextAwareDenormalizerInterface`, use `DenormalizerInterface` instead
 * Deprecate supporting denormalization for `AbstractUid` in `UidNormalizer`, use one of `AbstractUid` child class instead
 * Deprecate denormalizing to an abstract class in `UidNormalizer`
 * Add support for `can*()` methods to `ObjectNormalizer`

6.0
---

 * Remove `ArrayDenormalizer::setSerializer()`, call `setDenormalizer()` instead
 * Remove the ability to create instances of the annotation classes by passing an array of parameters, use named arguments instead

5.4
---

 * Add support of PHP backed enumerations
 * Add support for serializing empty array as object
 * Return empty collections as `ArrayObject` from `Serializer::normalize()` when `PRESERVE_EMPTY_OBJECTS` is set
 * Add support for collecting type errors during denormalization
 * Add missing arguments in `MissingConstructorArgumentsException`

5.3
---

 * Add the ability to provide (de)normalization context using metadata (e.g. `@Symfony\Component\Serializer\Annotation\Context`)
 * Deprecate `ArrayDenormalizer::setSerializer()`, call `setDenormalizer()` instead
 * Add normalization formats to `UidNormalizer`
 * Add `CsvEncoder::END_OF_LINE` context option
 * Deprecate creating instances of the annotation classes by passing an array of parameters, use named arguments instead

5.2.0
-----

 * added `CompiledClassMetadataFactory` and `ClassMetadataFactoryCompiler` for faster metadata loading.
 * added `UidNormalizer`
 * added `FormErrorNormalizer`
 * added `MimeMessageNormalizer`
 * serializer mapping can be configured using php attributes

5.1.0
-----

 * added support for scalar values denormalization
 * added support for `\stdClass` to `ObjectNormalizer`
 * added the ability to ignore properties using metadata (e.g. `@Symfony\Component\Serializer\Annotation\Ignore`)
 * added an option to serialize constraint violations payloads (e.g. severity)

5.0.0
-----

 * throw an exception when creating a `Serializer` with normalizers which neither implement `NormalizerInterface` nor `DenormalizerInterface`
 * throw an exception when creating a `Serializer` with encoders which neither implement `EncoderInterface` nor `DecoderInterface`
 * changed the default value of the `CsvEncoder` "as_collection" option to `true`
 * removed `AbstractNormalizer::$circularReferenceLimit`, `AbstractNormalizer::$circularReferenceHandler`,
   `AbstractNormalizer::$callbacks`, `AbstractNormalizer::$ignoredAttributes`,
   `AbstractNormalizer::$camelizedAttributes`, `AbstractNormalizer::setCircularReferenceLimit()`,
   `AbstractNormalizer::setCircularReferenceHandler()`, `AbstractNormalizer::setCallbacks()` and
   `AbstractNormalizer::setIgnoredAttributes()`, use the default context instead.
 * removed `AbstractObjectNormalizer::$maxDepthHandler` and `AbstractObjectNormalizer::setMaxDepthHandler()`,
   use the default context instead.
 * removed `XmlEncoder::setRootNodeName()` & `XmlEncoder::getRootNodeName()`, use the default context instead.
 * removed individual encoders/normalizers options as constructor arguments.
 * removed support for instantiating a `DataUriNormalizer` with a default MIME type guesser when the `symfony/mime` component isn't installed.
 * removed the `XmlEncoder::TYPE_CASE_ATTRIBUTES` constant. Use `XmlEncoder::TYPE_CAST_ATTRIBUTES` instead.

4.4.0
-----

 * deprecated the `XmlEncoder::TYPE_CASE_ATTRIBUTES` constant, use `XmlEncoder::TYPE_CAST_ATTRIBUTES` instead
 * added option to output a UTF-8 BOM in CSV encoder via `CsvEncoder::OUTPUT_UTF8_BOM_KEY` context option
 * added `ProblemNormalizer` to normalize errors according to the API Problem spec (RFC 7807)

4.3.0
-----

 * added the list of constraint violations' parameters in `ConstraintViolationListNormalizer`
 * added support for serializing `DateTimeZone` objects
 * added a `deep_object_to_populate` context option to recursive denormalize on `object_to_populate` object.

4.2.0
-----

 * using the default context is the new recommended way to configure normalizers and encoders
 * added a `skip_null_values` context option to not serialize properties with a `null` values
 * `AbstractNormalizer::handleCircularReference` is now final and receives
   two optional extra arguments: the format and the context
 * added support for XML comment encoding (encoding `['#comment' => ' foo ']` results `<!-- foo -->`)
 * added optional `int[] $encoderIgnoredNodeTypes` argument to `XmlEncoder::__construct`
   to configure node types to be ignored during encoding
 * added `AdvancedNameConverterInterface` to access the class,
   the format and the context in a name converter
 * the `AbstractNormalizer::handleCircularReference()` method will have two new `$format`
   and `$context` arguments in version 5.0, not defining them is deprecated
 * deprecated creating a `Serializer` with normalizers which do not implement
   either `NormalizerInterface` or `DenormalizerInterface`
 * deprecated creating a `Serializer` with normalizers which do not implement
   either `NormalizerInterface` or `DenormalizerInterface`
 * deprecated creating a `Serializer` with encoders which do not implement
   either `EncoderInterface` or `DecoderInterface`
 * added the optional `$objectClassResolver` argument in `AbstractObjectNormalizer`
   and `ObjectNormalizer` constructor
 * added `MetadataAwareNameConverter` to configure the serialized name of properties through metadata
 * `YamlEncoder` now handles the `.yml` extension too
 * `AbstractNormalizer::$circularReferenceLimit`, `AbstractNormalizer::$circularReferenceHandler`,
   `AbstractNormalizer::$callbacks`, `AbstractNormalizer::$ignoredAttributes`,
   `AbstractNormalizer::$camelizedAttributes`, `AbstractNormalizer::setCircularReferenceLimit()`,
   `AbstractNormalizer::setCircularReferenceHandler()`, `AbstractNormalizer::setCallbacks()` and
   `AbstractNormalizer::setIgnoredAttributes()` are deprecated, use the default context instead.
 * `AbstractObjectNormalizer::$maxDepthHandler` and `AbstractObjectNormalizer::setMaxDepthHandler()`
   are deprecated, use the default context instead.
 * passing configuration options directly to the constructor of `CsvEncoder`, `JsonDecode` and
   `XmlEncoder` is deprecated since Symfony 4.2, use the default context instead.

4.1.0
-----

 * added `CacheableSupportsMethodInterface` for normalizers and denormalizers that use
   only the type and the format in their `supports*()` methods
 * added `MissingConstructorArgumentsException` new exception for deserialization failure
   of objects that needs data insertion in constructor
 * added an optional `default_constructor_arguments` option of context to specify a default data in
   case the object is not initializable by its constructor because of data missing
 * added optional `bool $escapeFormulas = false` argument to `CsvEncoder::__construct`
 * added `AbstractObjectNormalizer::setMaxDepthHandler` to set a handler to call when the configured
   maximum depth is reached
 * added optional `int[] $ignoredNodeTypes` argument to `XmlEncoder::__construct`. XML decoding now
   ignores comment node types by default.
 * added `ConstraintViolationListNormalizer`

4.0.0
-----

 * removed the `SerializerAwareEncoder` and `SerializerAwareNormalizer` classes,
   use the `SerializerAwareTrait` instead
 * removed the `Serializer::$normalizerCache` and `Serializer::$denormalizerCache`
   properties
 * added an optional `string $format = null` argument to `AbstractNormalizer::instantiateObject`
 * added an optional `array $context = []` to `Serializer::supportsNormalization`, `Serializer::supportsDenormalization`,
   `Serializer::supportsEncoding` and `Serializer::supportsDecoding`

3.4.0
-----

 * added `AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT` context option
   to disable throwing an `UnexpectedValueException` on a type mismatch
 * added support for serializing `DateInterval` objects
 * added getter for extra attributes in `ExtraAttributesException`
 * improved `CsvEncoder` to handle variable nested structures
 * CSV headers can be passed to the `CsvEncoder` via the `csv_headers` serialization context variable
 * added `$context` when checking for encoding, decoding and normalizing in `Serializer`

3.3.0
-----

 * added `SerializerPass`

3.1.0
-----

 * added support for serializing objects that implement `JsonSerializable`
 * added the `DenormalizerAwareTrait` and `NormalizerAwareTrait` traits to
   support normalizer/denormalizer awareness
 * added the `DenormalizerAwareInterface` and `NormalizerAwareInterface`
   interfaces to support normalizer/denormalizer awareness
 * added a PSR-6 compatible adapter for caching metadata
 * added a `MaxDepth` option to limit the depth of the object graph when
   serializing objects
 * added support for serializing `SplFileInfo` objects
 * added support for serializing objects that implement `DateTimeInterface`
 * added `AbstractObjectNormalizer` as a base class for normalizers that deal
   with objects
 * added support to relation deserialization

2.7.0
-----

 * added support for serialization and deserialization groups including
   annotations, XML and YAML mapping.
 * added `AbstractNormalizer` to factorise code and ease normalizers development
 * added circular references handling for `PropertyNormalizer`
 * added support for a context key called `object_to_populate` in `AbstractNormalizer`
   to reuse existing objects in the deserialization process
 * added `NameConverterInterface` and `CamelCaseToSnakeCaseNameConverter`
 * [DEPRECATION] `GetSetMethodNormalizer::setCamelizedAttributes()` and
   `PropertyNormalizer::setCamelizedAttributes()` are replaced by
   `CamelCaseToSnakeCaseNameConverter`
 * [DEPRECATION] the `Exception` interface has been renamed to `ExceptionInterface`
 * added `ObjectNormalizer` leveraging the `PropertyAccess` component to normalize
   objects containing both properties and getters / setters / issers / hassers methods.
 * added `xml_type_cast_attributes` context option for allowing users to opt-out of typecasting
   xml attributes.

2.6.0
-----

 * added a new serializer: `PropertyNormalizer`. Like `GetSetMethodNormalizer`,
   this normalizer will map an object's properties to an array.
 * added circular references handling for `GetSetMethodNormalizer`

2.5.0
-----

 * added support for `is.*` getters in `GetSetMethodNormalizer`

2.4.0
-----

 * added `$context` support for XMLEncoder.
 * [DEPRECATION] JsonEncode and JsonDecode where modified to throw
   an exception if error found. No need for `get*Error()` functions

2.3.0
-----

 * added `GetSetMethodNormalizer::setCamelizedAttributes` to allow calling
   camel cased methods for underscored properties

2.2.0
-----

 * [BC BREAK] All Serializer, Normalizer and Encoder interfaces have been
   modified to include an optional `$context` array parameter.
 * The XML Root name can now be configured with the `xml_root_name`
   parameter in the context option to the `XmlEncoder`.
 * Options to `json_encode` and `json_decode` can be passed through
   the context options of `JsonEncode` and `JsonDecode` encoder/decoders.

2.1.0
-----

 * added DecoderInterface::supportsDecoding(),
   EncoderInterface::supportsEncoding()
 * removed NormalizableInterface::denormalize(),
   NormalizerInterface::denormalize(),
   NormalizerInterface::supportsDenormalization()
 * removed normalize() denormalize() encode() decode() supportsSerialization()
   supportsDeserialization() supportsEncoding() supportsDecoding()
   getEncoder() from SerializerInterface
 * Serializer now implements NormalizerInterface, DenormalizerInterface,
   EncoderInterface, DecoderInterface in addition to SerializerInterface
 * added DenormalizableInterface and DenormalizerInterface
 * [BC BREAK] changed `GetSetMethodNormalizer`'s key names from all lowercased
   to camelCased (e.g. `mypropertyvalue` to `myPropertyValue`)
 * [BC BREAK] convert the `item` XML tag to an array

    ``` xml
    <?xml version="1.0"?>
    <response>
        <item><title><![CDATA[title1]]></title></item><item><title><![CDATA[title2]]></title></item>
    </response>
    ```

    Before:

        Array()

    After:

        Array(
            [item] => Array(
                [0] => Array(
                    [title] => title1
                )
                [1] => Array(
                    [title] => title2
                )
            )
        )
