<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder;

use PHPStan\PhpDocParser\Parser\PhpDocParser;
use Psr\Container\ContainerInterface;
use Symfony\Component\JsonEncoder\DataModel\Encode\DataModelBuilder;
use Symfony\Component\JsonEncoder\Encode\EncodeAs;
use Symfony\Component\JsonEncoder\Encode\EncoderGenerator;
use Symfony\Component\JsonEncoder\Mapping\Encode\AttributePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\Encode\DateTimeTypePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\GenericTypePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PhpDocAwareReflectionTypeResolver;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonEncoder\Stream\StreamWriterInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 *
 * @implements EncoderInterface<array{
 *   stream?: StreamWriterInterface,
 *   max_depth?: int,
 *   date_time_format?: string,
 *   force_generation?: bool,
 * }>
 */
final readonly class JsonEncoder implements EncoderInterface
{
    private EncoderGenerator $encoderGenerator;

    public function __construct(
        private PropertyMetadataLoaderInterface $propertyMetadataLoader,
        string $cacheDir,
        private ?ContainerInterface $runtimeServices = null,
    ) {
        $this->encoderGenerator = new EncoderGenerator(new DataModelBuilder($propertyMetadataLoader, $runtimeServices), $cacheDir);
    }

    public function encode(mixed $data, Type $type, array $config = []): \Traversable&\Stringable
    {
        $stream = $config['stream'] ?? null;
        if (null !== $stream && method_exists($stream, 'getResource')) {
            $stream = $stream->getResource();
        }

        $path = $this->encoderGenerator->generate($type, match (true) {
            $stream instanceof StreamWriterInterface => EncodeAs::STREAM,
            null !== $stream => EncodeAs::RESOURCE,
            default => EncodeAs::STRING,
        }, $config);

        if (null !== $stream) {
            (require $path)($data, $stream, $config, $this->runtimeServices);

            return new Encoded(new \EmptyIterator());
        }

        return new Encoded((require $path)($data, $config, $this->runtimeServices));
    }

    public static function create(?string $cacheDir = null, ?ContainerInterface $runtimeServices = null): static
    {
        $cacheDir ??= sys_get_temp_dir() . '/json_encoder';

        try {
            $stringTypeResolver = new StringTypeResolver();
        } catch (\Throwable) {
        }

        $typeContextFactory = new TypeContextFactory($stringTypeResolver ?? null);
        $typeResolver = new PhpDocAwareReflectionTypeResolver(TypeResolver::create(), $typeContextFactory);

        return new static(new GenericTypePropertyMetadataLoader(
            new DateTimeTypePropertyMetadataLoader(
                new AttributePropertyMetadataLoader(
                    new PropertyMetadataLoader($typeResolver),
                    $typeResolver,
                ),
            ),
            $typeContextFactory,
        ), $cacheDir, $runtimeServices);
    }
}
