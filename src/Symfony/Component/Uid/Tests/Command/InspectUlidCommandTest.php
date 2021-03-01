<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Command\InspectUlidCommand;

final class InspectUlidCommandTest extends TestCase
{
    public function test()
    {
        $commandTester = new CommandTester(new InspectUlidCommand());

        $this->assertSame(1, $commandTester->execute(['ulid' => 'foobar']));
        $this->assertStringContainsString('Invalid ULID: "foobar"', $commandTester->getDisplay());

        foreach ([
            '01E439TP9XJZ9RPFH3T1PYBCR8',
            '1BKocMc5BnrVcuq2ti4Eqm',
            '0171069d-593d-97d3-8b3e-23d06de5b308',
        ] as $ulid) {
            $this->assertSame(0, $commandTester->execute(['ulid' => $ulid]));
            $this->assertSame(<<<EOF
 --------------------- -------------------------------------- 
  Label                 Value                                 
 --------------------- -------------------------------------- 
  Canonical (Base 32)   01E439TP9XJZ9RPFH3T1PYBCR8            
  Base 58               1BKocMc5BnrVcuq2ti4Eqm                
  RFC 4122              0171069d-593d-97d3-8b3e-23d06de5b308  
 --------------------- -------------------------------------- 
  Timestamp             2020-03-23 08:58:27.517               
 --------------------- -------------------------------------- 


EOF
                , $commandTester->getDisplay(true));
        }
    }
}
