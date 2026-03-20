<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Test\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\Mime\Email;

/**
 * @author Santiago San Martin <sanmartindev@gmail.com>
 */
final class EmailHtmlBodyMatchesRegex extends Constraint
{
    public function __construct(
        private string $expectedRegex,
    ) {
    }

    public function toString(): string
    {
        return \sprintf('matches HTML body against regex "%s"', $this->expectedRegex);
    }

    protected function matches($other): bool
    {
        if (!$other instanceof Email) {
            throw new \LogicException('Can only test a message html body on an Email instance.');
        }

        return (bool) preg_match(\sprintf('{%s}', $this->expectedRegex), $other->getHtmlBody());
    }

    protected function failureDescription($other): string
    {
        $message = 'The Email HTML body '.$this->toString();

        if ($other instanceof Email) {
            $message .= \sprintf('. The HTML body was: "%s"', $other->getHtmlBody());
        }

        return $message;
    }
}
