<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * Associates a name that can be exploited by NameTopicsResolver with the message.
 *
 * @see NameBasedHandlersLocator
 *
 * @author Pascal Luna <skalpa@zetareticuli.org>
 *
 * @experimental in 4.2
 */
final class NameStamp implements StampInterface
{
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
