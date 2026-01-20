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
 * Stamp applied to messages to indicate that the message body will be compressed
 * before being sent, optimizing transmission and storage efficiency.
 *
 * @author Santiago San Martin <sanmartindev@gmail.com>
 */
final class CompressStamp implements StampInterface
{
}
