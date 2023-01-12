<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Exception;

/**
 * This exception is thrown when a circular dependency is detected during event listeners/subscribers initialization.
 * It may be fixed by making code or service lazy.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
class EventListenerCircularException extends \RuntimeException
{
    public function setMessage(string $message): self
    {
        if ($this->message) {
            throw new \LogicException('EventSubscriberCircularException message cannot be set more than once.', 0, $this);
        }

        $this->message = $message;

        return $this;
    }
}
