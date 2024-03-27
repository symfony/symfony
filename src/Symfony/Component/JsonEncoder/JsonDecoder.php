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

use Psr\Container\ContainerInterface;
use Symfony\Component\JsonEncoder\DataModel\Decode\DataModelBuilder;
use Symfony\Component\JsonEncoder\Decode\DecodeFrom;
use Symfony\Component\JsonEncoder\Decode\DecoderGenerator;
use Symfony\Component\JsonEncoder\Decode\Instantiator;
use Symfony\Component\JsonEncoder\Decode\LazyInstantiator;
use Symfony\Component\JsonEncoder\Mapping\Decode\AttributePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\Decode\DateTimeTypePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PhpDocAwareReflectionTypeResolver;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\GenericTypePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonEncoder\Stream\BufferedStream;
use Symfony\Component\JsonEncoder\Stream\StreamReaderInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 *
 * @implements DecoderInterface<array{
 *   date_time_format?: string,
 *   force_generation?: bool,
 * }>
 */
final readonly class JsonDecoder implements DecoderInterface
{
    private DecoderGenerator $decoderGenerator;
    private Instantiator $instantiator;
    private LazyInstantiator $lazyInstantiator;

    public function __construct(
        PropertyMetadataLoaderInterface $propertyMetadataLoader,
        string $cacheDir,
        private ?ContainerInterface $runtimeServices = null,
    ) {
        $this->decoderGenerator = new DecoderGenerator(new DataModelBuilder($propertyMetadataLoader, $runtimeServices), $cacheDir);
        $this->instantiator = new Instantiator();
        $this->lazyInstantiator = new LazyInstantiator($cacheDir);
    }

    public function decode(StreamReaderInterface|\Traversable|\Stringable|string $input, Type $type, array $config = []): mixed
    {
        if ($input instanceof \Traversable && !$input instanceof StreamReaderInterface) {
            $chunks = $input;
            $input = new BufferedStream();
            foreach ($chunks as $chunk) {
                $input->write($chunk);
            }
        }

        $isStream = $input instanceof StreamReaderInterface;
        $isResourceStream = $isStream && method_exists($input, 'getResource');

        $path = $this->decoderGenerator->generate($type, match (true) {
            $isResourceStream => DecodeFrom::RESOURCE,
            $isStream => DecodeFrom::STREAM,
            default => DecodeFrom::STRING,
        }, $config);

        return (require $path)($isResourceStream ? $input->getResource() : $input, $config, $isStream ? $this->lazyInstantiator : $this->instantiator, $this->runtimeServices);
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
