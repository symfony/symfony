<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Cron;

use Symfony\Component\Scheduler\Transport\TransportInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CronFactory
{
    private $registry;

    public function __construct(CronRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function create(string $name, TransportInterface $transport, array $extraOptions = []): void
    {
        $this->registry->register($name, new Cron($name, array_merge($transport->getOptions(), $extraOptions)));
    }
}
