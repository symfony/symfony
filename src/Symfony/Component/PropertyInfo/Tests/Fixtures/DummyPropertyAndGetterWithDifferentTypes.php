<?php

declare(strict_types=1);

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

final readonly class DummyPropertyAndGetterWithDifferentTypes
{
    public function __construct(
        /**
         * @var string
         */
        private string $foo,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function getFoo(): array
    {
        return (array)$this->foo;
    }
}
