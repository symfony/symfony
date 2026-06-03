<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Test;

use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\JsonPath\JsonCrawler;
use Symfony\Component\JsonPath\JsonPath;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
class JsonPathSame extends Constraint
{
    public function __construct(
        private JsonPath|string $jsonPath,
        private string $json,
    ) {
    }

    public function toString(): string
    {
        return \sprintf('is identical to JSON path "%s" result', $this->jsonPath);
    }

    protected function matches(mixed $other): bool
    {
        return (new JsonCrawler($this->json))->find($this->jsonPath) === $other;
    }
}
