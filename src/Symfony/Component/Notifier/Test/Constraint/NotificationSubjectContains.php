<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Test\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\Notifier\Message\MessageInterface;

/**
 * @author Smaïne Milianni <smaine.milianni@gmail.com>
 */
final class NotificationSubjectContains extends Constraint
{
    public function __construct(
        private string $expectedText,
    ) {
    }

    public function toString(): string
    {
        return \sprintf('contains "%s"', $this->expectedText);
    }

    /**
     * @param MessageInterface $message
     */
    protected function matches($message): bool
    {
        return false !== mb_strpos($message->getSubject(), $this->expectedText);
    }

    /**
     * @param MessageInterface $message
     */
    protected function failureDescription($message): string
    {
        return 'the Notification subject '.$this->toString();
    }
}
