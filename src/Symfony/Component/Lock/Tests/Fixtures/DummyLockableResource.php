<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Fixtures;

use Symfony\Component\Lock\LockableResourceInterface;
use Symfony\Component\Lock\LockableResourceTrait;

class DummyLockableResource implements LockableResourceInterface
{
    use LockableResourceTrait;
}
