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

use PHPUnit\Event\TestSuite\Loaded;
use PHPUnit\Event\TestSuite\LoadedSubscriber;
use PHPUnit\Metadata\Group;
use Symfony\Bridge\PhpUnit\DnsMock;

/**
 * @internal
 */
class RegisterDnsMockSubscriber implements LoadedSubscriber
{
    public function notify(Loaded $event): void
    {
        foreach ($event->testSuite()->tests() as $test) {
            foreach ($test->metadata() as $metadata) {
                if ($metadata instanceof Group && 'dns-sensitive' === $metadata->groupName()) {
                    DnsMock::register($test->className());
                }
            }
        }
    }
}
