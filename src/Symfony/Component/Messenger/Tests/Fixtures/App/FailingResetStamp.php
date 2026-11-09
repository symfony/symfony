<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Fixtures\App;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Makes the reset of services fail in the worker that handles the message.
 */
final class FailingResetStamp implements StampInterface
{
}
