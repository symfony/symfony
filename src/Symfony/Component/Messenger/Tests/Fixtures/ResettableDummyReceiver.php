<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Contracts\Service\ResetInterface;

class ResettableDummyReceiver extends DummyReceiver implements ResetInterface
{
    private bool $hasBeenReset = false;

    public function reset(): void
    {
        $this->hasBeenReset = true;
    }

    public function hasBeenReset(): bool
    {
        return $this->hasBeenReset;
    }
}
