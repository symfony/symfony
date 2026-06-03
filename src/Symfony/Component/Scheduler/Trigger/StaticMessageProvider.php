<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Trigger;

use Symfony\Component\Scheduler\Generator\MessageContext;

final class StaticMessageProvider implements MessageProviderInterface, \Stringable
{
    /**
     * @param array<object> $messages
     */
    public function __construct(
        private array $messages,
        private string $id = '',
        private string $description = '',
    ) {
    }

    public function getMessages(MessageContext $context): iterable
    {
        return $this->messages;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return $this->description ?: $this->id;
    }
}
