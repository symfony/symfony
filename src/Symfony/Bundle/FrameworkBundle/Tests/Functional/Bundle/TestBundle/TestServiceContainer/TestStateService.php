<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\TestServiceContainer;

class TestStateService
{
    public static int $clearCount = 0;

    private array $records = [];

    public function record(string $record): void
    {
        $this->records[] = $record;
    }

    public function getRecords(): array
    {
        return $this->records;
    }

    public function reset(): void
    {
        $this->records = [];
    }

    public function clear(): void
    {
        ++self::$clearCount;
    }
}
