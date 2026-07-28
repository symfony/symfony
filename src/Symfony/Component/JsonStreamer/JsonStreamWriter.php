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
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\JsonStreamer\Mapping\GenericTypePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonStreamer\Mapping\Write\AttributePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Transformer\BcMathNumberValueObjectTransformer;
use Symfony\Component\JsonStreamer\Transformer\DateIntervalValueObjectTransformer;
use Symfony\Component\JsonStreamer\Transformer\DateTimeValueObjectTransformer;
use Symfony\Component\JsonStreamer\Transformer\DateTimeZoneValueObjectTransformer;
use Symfony\Component\JsonStreamer\Transformer\GmpNumberValueObjectTransformer;
use Symfony\Component\JsonStreamer\Transformer\PropertyValueTransformerInterface;
use Symfony\Component\JsonStreamer\Transformer\UlidValueObjectTransformer;
use Symfony\Component\JsonStreamer\Transformer\UuidValueObjectTransformer;
use Symfony\Component\JsonStreamer\Transformer\ValueObjectTransformerInterface;
use Symfony\Component\JsonStreamer\Write\StreamWriterGenerator;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @psalm-type Options = array{
 *     date_time_format?: string,
 *     date_time_timezone?: string|\DateTimeZone,
 *     date_interval_format?: string,
 *     uid_format?: string,
 *     include_null_properties?: bool,
 *     cache_variant?: string,
 *     ...<string, mixed>,
 * }
 *
 * The "cache_variant" option does not change the output on its own. It is appended to the
 * name of the generated code cache file, so that a custom property metadata loader can
 * produce several payload shapes for one PHP type without them overwriting each other.
 * It must match "[a-zA-Z0-9_-]+".
 *
 * @implements StreamWriterInterface<Options>
 */
final class JsonStreamWriter implements StreamWriterInterface
{
    private StreamWriterGenerator $streamWriterGenerator;

    /**
     * @var array<string, callable>
     */
    private array $streamWriters = [];

    /**
     * @param Options $defaultOptions
     */
    public function __construct(
        private ContainerInterface $transformers,
        PropertyMetadataLoaderInterface $propertyMetadataLoader,
        string $streamWritersDir,
        ?ConfigCacheFactoryInterface $configCacheFactory = null,
        private array $defaultOptions = [],
    ) {
        $this->streamWriterGenerator = new StreamWriterGenerator($propertyMetadataLoader, $transformers, $streamWritersDir, $configCacheFactory);
    }

    public function write(mixed $data, Type $type, array $options = []): \Traversable&\Stringable
    {
        $options += $this->defaultOptions;
        $path = $this->streamWriterGenerator->generate($type, $options);
        $chunks = ($this->streamWriters[$path] ??= require $path)($data, $this->transformers, $options);

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
     * @param array<string, PropertyValueTransformerInterface|ValueObjectTransformerInterface> $transformers
     */
    public static function create(array $transformers = [], ?string $streamWritersDir = null): self
    {
        $streamWritersDir ??= sys_get_temp_dir().'/json_streamer/write';
        $transformers += [
            \DateTimeInterface::class => new DateTimeValueObjectTransformer(),
            \DateInterval::class => new DateIntervalValueObjectTransformer(),
            \DateTimeZone::class => new DateTimeZoneValueObjectTransformer(),
            \BcMath\Number::class => new BcMathNumberValueObjectTransformer(),
            \GMP::class => new GmpNumberValueObjectTransformer(),
            Uuid::class => new UuidValueObjectTransformer(),
            Ulid::class => new UlidValueObjectTransformer(),
        ];

        $transformersContainer = new class($transformers) implements ContainerInterface {
            public function __construct(
                private array $transformers,
            ) {
            }

            public function has(string $id): bool
            {
                return isset($this->transformers[$id]);
            }

            public function get(string $id): PropertyValueTransformerInterface|ValueObjectTransformerInterface
            {
                return $this->transformers[$id];
            }
        };

        $typeContextFactory = new TypeContextFactory(class_exists(PhpDocParser::class) ? new StringTypeResolver() : null);

        $propertyMetadataLoader = new GenericTypePropertyMetadataLoader(
            new AttributePropertyMetadataLoader(
                new PropertyMetadataLoader(TypeResolver::create()),
                $transformersContainer,
                TypeResolver::create(),
            ),
            $typeContextFactory,
        );

        return new self($transformersContainer, $propertyMetadataLoader, $streamWritersDir);
    }
}
