<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Extension;

use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Metadata\Group;
use Symfony\Bridge\PhpUnit\DnsMock;

/**
 * @internal
 */
class DisableDnsMockSubscriber implements FinishedSubscriber
{
    public function notify(Finished $event): void
    {
        $test = $event->test();

        if (!$test instanceof TestMethod) {
            return;
        }

        foreach ($test->metadata() as $metadata) {
            if ($metadata instanceof Group && 'dns-sensitive' === $metadata->groupName()) {
                DnsMock::withMockedHosts([]);
            }
        }
    }
}
