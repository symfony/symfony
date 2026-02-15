<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests\Formatter;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Formatter\ConsoleFormatter;
use Symfony\Bridge\Monolog\Tests\RecordFactory;

class ConsoleFormatterTest extends TestCase
{
    public function testFormat()
    {
        $record = RecordFactory::create(datetime: new \DateTimeImmutable('2013-01-13 12:34:56 Europe/Berlin'));
        $formatter = new ConsoleFormatter();

        self::assertSame("12:34:56 <fg=cyan>WARNING  </> <comment>[test]</> test\n", $formatter->format($record));
    }
}
