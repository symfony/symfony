<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Stamp;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\ValidationStamp;
use Symfony\Component\Validator\Constraints\GroupSequence;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class ValidationStampTest extends TestCase
{
    public function testStamp()
    {
        $stamp = new ValidationStamp($groups = array('Default', 'Extra'));
        $this->assertSame($groups, $stamp->getGroups());

        $stamp = new ValidationStamp($groups = new GroupSequence(array('Default', 'Then')));
        $this->assertSame($groups, $stamp->getGroups());
    }

    public function testSerializable()
    {
        $this->assertEquals($stamp = new ValidationStamp(array('Default', 'Extra')), unserialize(serialize($stamp)));
        $this->assertEquals($stamp = new ValidationStamp(new GroupSequence(array('Default', 'Then'))), unserialize(serialize($stamp)));
    }
}
