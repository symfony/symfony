CHANGELOG
=========

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
