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
use Symfony\Component\JsonStreamer\Mapping\Write\AttributePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\Write\DateTimeTypePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\ValueTransformer\DateTimeToStringValueTransformer;
use Symfony\Component\JsonStreamer\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\JsonStreamer\Write\StreamWriterGenerator;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @implements StreamWriterInterface<array<string, mixed>>
 *
 * @experimental
 */
final class JsonStreamWriter implements StreamWriterInterface
{
    private StreamWriterGenerator $streamWriterGenerator;

    public function __construct(
        private ContainerInterface $valueTransformers,
        PropertyMetadataLoaderInterface $propertyMetadataLoader,
        string $streamWritersDir,
    ) {
        $this->streamWriterGenerator = new StreamWriterGenerator($propertyMetadataLoader, $streamWritersDir);
    }

    public function write(mixed $data, Type $type, array $options = []): \Traversable&\Stringable
    {
        $path = $this->streamWriterGenerator->generate($type, $options);
        $chunks = (require $path)($data, $this->valueTransformers, $options);

        return new
        /**
         * @implements \IteratorAggregate<int, string>
         */
        class($chunks) implements \IteratorAggregate, \Stringable {
            /**
             * @param \Traversable<string> $chunks
             */
            public function __construct(
                private \Traversable $chunks,
            ) {
            }

            public function getIterator(): \Traversable
            {
                return $this->chunks;
            }

            public function __toString(): string
            {
                $string = '';
                foreach ($this->chunks as $chunk) {
                    $string .= $chunk;
                }

                return $string;
            }
        };
    }

    /**
     * @param array<string, ValueTransformerInterface> $valueTransformers
     */
    public static function create(array $valueTransformers = [], ?string $streamWritersDir = null): self
    {
        $streamWritersDir ??= sys_get_temp_dir().'/json_streamer/write';
        $valueTransformers += [
            'json_streamer.value_transformer.date_time_to_string' => new DateTimeToStringValueTransformer(),
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

        return new self($valueTransformersContainer, $propertyMetadataLoader, $streamWritersDir);
    }
}
