<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process\Contracts;

/**
 * Command executor interface.
 *
 * @author Pululu Kinanga Andre <pululuandre@gmail.com>
 */
interface CommandExecutorInterface
{
    public function execute(string $command): ?string;
}
