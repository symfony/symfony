<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
interface SessionFactoryInterface
{
    /**
     * Creates a new instance of SessionInterface.
     */
    public function createSession(): SessionInterface;
}
