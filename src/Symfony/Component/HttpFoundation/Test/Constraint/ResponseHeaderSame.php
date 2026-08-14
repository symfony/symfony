<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Test\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\HttpFoundation\Response;

final class ResponseHeaderSame extends Constraint
{
    private ?string $actualValue = null;

    public function __construct(
        private string $headerName,
        private string $expectedValue,
    ) {
    }

    public function toString(): string
    {
        return \sprintf('has header "%s" with value "%s"', $this->headerName, $this->expectedValue);
    }

    /**
     * @param Response $response
     */
    protected function matches($response): bool
    {
        $this->actualValue = $response->headers->get($this->headerName, null);

        return $this->expectedValue === $this->actualValue;
    }

    /**
     * @param Response $response
     */
    protected function failureDescription($response): string
    {
        $description = 'the Response '.$this->toString();

        if (null === $this->actualValue) {
            return $description.\sprintf(', header "%s" is not set', $this->headerName);
        }

        // nothing to add when both values match, which is the case when the assertion is negated
        if ($this->expectedValue !== $this->actualValue) {
            $description .= \sprintf(', value of header "%s" is "%s"', $this->headerName, $this->actualValue);
        }

        return $description;
    }
}
