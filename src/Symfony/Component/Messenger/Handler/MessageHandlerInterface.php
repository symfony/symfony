<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Handler;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Marker interface for message handlers.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @deprecated since Symfony 6.2, use the {@see AsMessageHandler} attribute instead
 */
interface MessageHandlerInterface
{
}
