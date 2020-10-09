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
 * Apply this stamp to check the message authorization in the bus.
 *
 * @see \Symfony\Component\Messenger\Middleware\AuthorizationCheckerMiddleware
 *
 * @author Maxime Perrimond <max.perrimond@gmail.com>
 */
final class AuthorizationAttributeStamp implements StampInterface
{
    private $attribute;

    public function __construct(string $attribute)
    {
        $this->attribute = $attribute;
    }

    public function getAttribute(): string
    {
        return $this->attribute;
    }
}
