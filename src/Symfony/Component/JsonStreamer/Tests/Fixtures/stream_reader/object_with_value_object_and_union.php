<?php

return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueObjectAndUnion'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueObjectAndUnion::class, \array_filter(['dateTimeOrInt' => \array_key_exists('dateTimeOrInt', $data) ? $providers['DateTimeInterface|int']($data['dateTimeOrInt']) : '_symfony_missing_value'], static function ($v) {
            return '_symfony_missing_value' !== $v;
        }));
    };
    $providers['DateTimeInterface'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        return $valueTransformers->get('json_streamer.value_transformer.string_to_date_time')->transform($data, $options);
    };
    $providers['DateTimeInterface|int'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        if (\is_string($data)) {
            return $providers['DateTimeInterface']($data);
        }
        if (\is_int($data)) {
            return $data;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "DateTimeInterface|int".', \get_debug_type($data)));
    };
    return $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueObjectAndUnion'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
