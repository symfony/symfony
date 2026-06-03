<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath;

use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

final class JsonPathCrawler implements JsonPathCrawlerInterface
{
    /**
     * @param ContainerInterface|ServiceProviderInterface<callable(mixed ...$arguments): mixed>|null $functionsProvider
     * @param array<string, array{arity?: int|null, return_type?: FunctionReturnType|null}>          $functionsMetadata
     */
    public function __construct(
        private readonly ?ContainerInterface $functionsProvider = null,
        private readonly array $functionsMetadata = [],
    ) {
    }

    /**
     * @param resource|string $raw
     */
    public function crawl(mixed $raw): JsonCrawlerInterface
    {
        return new JsonCrawler($raw, $this->functionsProvider, $this->functionsMetadata);
    }
}
