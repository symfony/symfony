<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\State;

interface StateFactoryInterface
{
    /**
     * @param array<string, int|float|string|bool|null> $options
     */
    public function create(string $scheduleName, array $options): StateInterface;
}
