<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Metadata;

/**
 * Keeps the class metadata returned by the decorated factory in memory for all the instances created with the same key.
 *
 * @phpstan-import-type ClassMetadata from ClassMetadataFactoryInterface
 *
 * @internal
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class SharedClassMetadataFactory implements ClassMetadataFactoryInterface
{
    /**
     * @var array<string, array<class-string, array<class-string, ClassMetadata>>>
     */
    private static array $loaded = [];

    public function __construct(
        private readonly ClassMetadataFactoryInterface $decorated,
        private readonly string $key,
    ) {
    }

    public function create(object $source, object $target): array
    {
        return self::$loaded[$this->key][$source::class][$target::class] ??= $this->decorated->create($source, $target);
    }
}
