<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Fixtures;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Service\ShutdownInterface;

final class ShutdownAwareAdapter extends ArrayAdapter implements ShutdownInterface
{
    public array $resets = [];

    public function reset(bool $shutdown = false): void
    {
        $this->resets[] = $shutdown;
        parent::reset();
    }
}
