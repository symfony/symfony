<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(name: 'foo:lock5')]
class FooLock5InvokableCommand
{
    use LockableTrait;

    public function __construct(
        LockFactory $lockFactory,
        private readonly ?float $ttl,
    ) {
        $this->lockFactory = $lockFactory;
    }

    public function __invoke(): int
    {
        if (!$this->lock(null, false, $this->ttl)) {
            return 1;
        }

        $this->release();

        return 2;
    }
}
