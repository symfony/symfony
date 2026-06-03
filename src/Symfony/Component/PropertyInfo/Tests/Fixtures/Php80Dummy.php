<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

class Php80Dummy
{
    public mixed $mixedProperty;

    /**
     * @param string $promotedAndMutated
     * @param string $promotedWithDocComment
     * @param string $promotedWithDocCommentAndType
     * @param array<string> $collection
     */
    public function __construct(
        private mixed $promoted,
        private mixed $promotedAndMutated,
        /**
         * Comment without @var.
         */
        private mixed $promotedWithDocComment,
        /**
         * @var int
         */
        private mixed $promotedWithDocCommentAndType,
        private array $collection,
    )
    {
    }

    public function getFoo(): array|null
    {
    }

    public function setBar(int|null $bar)
    {
    }

    public function setTimeout(int|float $timeout)
    {
    }

    public function getOptional(): int|float|null
    {
    }

    public function setString(string|\Stringable $string)
    {
    }

    public function setPayload(mixed $payload)
    {
    }

    public function getData(): mixed
    {
    }
}
