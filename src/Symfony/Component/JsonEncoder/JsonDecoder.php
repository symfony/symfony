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
use Symfony\Component\JsonEncoder\Decode\DecoderGenerator;
use Symfony\Component\JsonEncoder\Decode\Denormalizer\DateTimeDenormalizer;
use Symfony\Component\JsonEncoder\Decode\Denormalizer\DenormalizerInterface;
use Symfony\Component\JsonEncoder\Decode\Instantiator;
use Symfony\Component\JsonEncoder\Decode\LazyInstantiator;
use Symfony\Component\JsonEncoder\Mapping\Decode\AttributePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\Decode\DateTimeTypePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\GenericTypePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @implements DecoderInterface<array<string, mixed>>
 *
 * @experimental
 */
final class JsonDecoder implements DecoderInterface
{
    private DecoderGenerator $decoderGenerator;
    private Instantiator $instantiator;
    private LazyInstantiator $lazyInstantiator;

    public function __construct(
        private ContainerInterface $denormalizers,
        PropertyMetadataLoaderInterface $propertyMetadataLoader,
        string $decodersDir,
        string $lazyGhostsDir,
    ) {
        $this->decoderGenerator = new DecoderGenerator($propertyMetadataLoader, $decodersDir);
        $this->instantiator = new Instantiator();
        $this->lazyInstantiator = new LazyInstantiator($lazyGhostsDir);
    }

    public function decode($input, Type $type, array $options = []): mixed
    {
        $isStream = \is_resource($input);
        $path = $this->decoderGenerator->generate($type, $isStream, $options);

        return (require $path)($input, $this->denormalizers, $isStream ? $this->lazyInstantiator : $this->instantiator, $options);
    }

    /**
     * @param array<string, DenormalizerInterface> $denormalizers
     */
    public static function create(array $denormalizers = [], ?string $decodersDir = null, ?string $lazyGhostsDir = null): self
    {
        $decodersDir ??= sys_get_temp_dir().'/json_encoder/decoder';
        $lazyGhostsDir ??= sys_get_temp_dir().'/json_encoder/lazy_ghost';
        $denormalizers += [
            'json_encoder.denormalizer.date_time' => new DateTimeDenormalizer(immutable: false),
            'json_encoder.denormalizer.date_time_immutable' => new DateTimeDenormalizer(immutable: true),
        ];

        $denormalizersContainer = new class($denormalizers) implements ContainerInterface {
            public function __construct(
                private array $denormalizers,
            ) {
            }

            public function has(string $id): bool
            {
                return isset($this->denormalizers[$id]);
            }

            public function get(string $id): DenormalizerInterface
            {
                return $this->denormalizers[$id];
            }
        };

        $typeContextFactory = new TypeContextFactory(class_exists(PhpDocParser::class) ? new StringTypeResolver() : null);

        $propertyMetadataLoader = new GenericTypePropertyMetadataLoader(
            new DateTimeTypePropertyMetadataLoader(
                new AttributePropertyMetadataLoader(
                    new PropertyMetadataLoader(TypeResolver::create()),
                    $denormalizersContainer,
                    TypeResolver::create(),
                ),
            ),
            $typeContextFactory,
        );

        return new self($denormalizersContainer, $propertyMetadataLoader, $decodersDir, $lazyGhostsDir);
    }
}
