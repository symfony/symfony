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
class JsonPathContains extends Constraint
{
    public function __construct(
        private JsonPath|string $jsonPath,
        private string $json,
        private bool $strict = true,
    ) {
    }

    public function toString(): string
    {
        return \sprintf('is found in elements at JSON path "%s"', $this->jsonPath);
    }

    protected function matches(mixed $other): bool
    {
        $result = (new JsonCrawler($this->json))->find($this->jsonPath);

        return \in_array($other, $result, $this->strict);
    }
}
