<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Storage\Handler;

/** @author Julien Robic <nayte91@gmail.com> */
interface ClearableSessionHandlerInterface
{
    /** Removes all sessions from the storage. */
    public function clear(): void;
}
