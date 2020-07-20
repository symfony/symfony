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
use Symfony\Component\Mime\RawMessage;

final class EmailHasHeader extends Constraint
{
    private $headerName;

    public function __construct(string $headerName)
    {
        $this->headerName = $headerName;
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return sprintf('has header "%s"', $this->headerName);
    }

    /**
     * @param RawMessage $message
     *
     * {@inheritdoc}
     */
    protected function matches($message): bool
    {
        if (RawMessage::class === \get_class($message)) {
            throw new \LogicException('Unable to test a message header on a RawMessage instance.');
        }

        return $message->getHeaders()->has($this->headerName);
    }

    /**
     * @param RawMessage $message
     *
     * {@inheritdoc}
     */
    protected function failureDescription($message): string
    {
        return 'the Email '.$this->toString();
    }
}
