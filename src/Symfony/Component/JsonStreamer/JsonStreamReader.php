<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer;

use PHPStan\PhpDocParser\Parser\PhpDocParser;
use Psr\Container\ContainerInterface;
use Symfony\Component\JsonStreamer\Mapping\GenericTypePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonStreamer\Mapping\Read\AttributePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\Read\DateTimeTypePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Read\Instantiator;
use Symfony\Component\JsonStreamer\Read\LazyInstantiator;
use Symfony\Component\JsonStreamer\Read\StreamReaderGenerator;
use Symfony\Component\JsonStreamer\ValueTransformer\StringToDateTimeValueTransformer;
use Symfony\Component\JsonStreamer\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @implements StreamReaderInterface<array<string, mixed>>
 *
 * @experimental
 */
final class JsonStreamReader implements StreamReaderInterface
{
    private StreamReaderGenerator $streamReaderGenerator;
    private Instantiator $instantiator;
    private LazyInstantiator $lazyInstantiator;

    public function __construct(
        private ContainerInterface $valueTransformers,
        PropertyMetadataLoaderInterface $propertyMetadataLoader,
        string $streamReadersDir,
        string $lazyGhostsDir,
    ) {
        $this->streamReaderGenerator = new StreamReaderGenerator($propertyMetadataLoader, $streamReadersDir);
        $this->instantiator = new Instantiator();
        $this->lazyInstantiator = new LazyInstantiator($lazyGhostsDir);
    }

    public function read($input, Type $type, array $options = []): mixed
    {
        $isStream = \is_resource($input);
        $path = $this->streamReaderGenerator->generate($type, $isStream, $options);

        return (require $path)($input, $this->valueTransformers, $isStream ? $this->lazyInstantiator : $this->instantiator, $options);
    }

    /**
     * @param array<string, ValueTransformerInterface> $valueTransformers
     */
    public static function create(array $valueTransformers = [], ?string $streamReadersDir = null, ?string $lazyGhostsDir = null): self
    {
        $streamReadersDir ??= sys_get_temp_dir().'/json_streamer/read';
        $lazyGhostsDir ??= sys_get_temp_dir().'/json_streamer/lazy_ghost';
        $valueTransformers += [
            'json_streamer.value_transformer.string_to_date_time' => new StringToDateTimeValueTransformer(),
        ];

        $valueTransformersContainer = new class($valueTransformers) implements ContainerInterface {
            public function __construct(
                private array $valueTransformers,
            ) {
            }

            public function has(string $id): bool
            {
                return isset($this->valueTransformers[$id]);
            }

            public function get(string $id): ValueTransformerInterface
            {
                return $this->valueTransformers[$id];
            }
        };

        $typeContextFactory = new TypeContextFactory(class_exists(PhpDocParser::class) ? new StringTypeResolver() : null);

        $propertyMetadataLoader = new GenericTypePropertyMetadataLoader(
            new DateTimeTypePropertyMetadataLoader(
                new AttributePropertyMetadataLoader(
                    new PropertyMetadataLoader(TypeResolver::create()),
                    $valueTransformersContainer,
                    TypeResolver::create(),
                ),
            ),
            $typeContextFactory,
        );

        return new self($valueTransformersContainer, $propertyMetadataLoader, $streamReadersDir, $lazyGhostsDir);
    }
}
