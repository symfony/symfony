<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Console\Tester\CommandTester;

class SingleCommandApplicationTest extends TestCase
{
    public function testRun()
    {
        $command = new class extends SingleCommandApplication {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        $command->setAutoExit(false);
        $this->assertSame(0, (new CommandTester($command))->execute([]));
    }
}
