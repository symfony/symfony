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
    private string $headerName;
    private string $expectedValue;
    private bool $logicalNot;
    private ?string $actualValue = null;

    public function __construct(string $headerName, string $expectedValue, bool $logicalNot = false)
    {
        $this->headerName = $headerName;
        $this->expectedValue = $expectedValue;
        $this->logicalNot = $logicalNot;
    }

    public function toString(): string
    {
        $output = sprintf('has header "%s" with value "%s"', $this->headerName, $this->expectedValue);

        if (null === $this->actualValue) {
            $output .= sprintf(', header "%s" is not set', $this->headerName);
        }

        if (null !== $this->actualValue && !$this->logicalNot) {
            $output .= sprintf(', value of header "%s" is "%s"', $this->headerName, $this->actualValue);
        }

        return $output;
    }

    /**
     * @param Response $response
     */
    protected function matches($response): bool
    {
        $this->actualValue = $response->headers->get($this->headerName);

        return $this->expectedValue === $this->actualValue;
    }

    /**
     * @param Response $response
     */
    protected function failureDescription($response): string
    {
        return 'the Response '.$this->toString();
    }
}
