<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Task;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ShellTask extends AbstractTask
{
    public function __construct(string $name, string $command, array $options = [], array $additionalOptions = [])
    {
        parent::__construct($name, array_merge($options, [
            'command' => $command,
            'type' => 'shell',
        ]), array_merge([
            'cwd' => ['string', 'null'],
            'env' => ['array', 'null'],
            'timeout' => ['int', 'float', 'null'],
        ], $additionalOptions));
    }
}
