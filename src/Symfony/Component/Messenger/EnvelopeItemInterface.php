<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

/**
 * An envelope item related to a message.
 * This item must be serializable for transport.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @experimental in 4.1
 */
interface EnvelopeItemInterface extends \Serializable
{
}
