<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Test\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\Mailer\Event\MessageEvents;

final class EmailCount extends Constraint
{
    private $expectedValue;
    private $transport;

    public function __construct(int $expectedValue, string $transport = null)
    {
        $this->expectedValue = $expectedValue;
        $this->transport = $transport;
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return sprintf('%shas sent "%d" emails', $this->transport ? $this->transport.' ' : '', $this->expectedValue);
    }

    /**
     * @param MessageEvents $events
     *
     * {@inheritdoc}
     */
    protected function matches($events): bool
    {
        return $this->expectedValue === \count($events->getEvents($this->transport));
    }

    /**
     * @param MessageEvents $events
     *
     * {@inheritdoc}
     */
    protected function failureDescription($events): string
    {
        return sprintf('the Transport %s (%d sent)', $this->toString(), \count($events->getEvents($this->transport)));
    }
}
