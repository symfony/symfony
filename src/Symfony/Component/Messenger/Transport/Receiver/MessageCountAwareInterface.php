<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Receiver;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
interface MessageCountAwareInterface
{
    /**
     * Returns the number of messages waiting to be handled.
     *
     * In some systems, this may be an approximate number.
     */
    public function getMessageCount(): int;
}
