<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Validator\Constraints\Email;

class EmailTest extends TestCase
{
    /**
     * @expectedDeprecation The "strict" property is deprecated since Symphony 4.1. Use "mode"=>"strict" instead.
     * @group legacy
     */
    public function testLegacyConstructorStrict()
    {
        $subject = new Email(array('strict' => true));

        $this->assertTrue($subject->strict);
    }

    public function testConstructorStrict()
    {
        $subject = new Email(array('mode' => Email::VALIDATION_MODE_STRICT));

        $this->assertEquals(Email::VALIDATION_MODE_STRICT, $subject->mode);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The "mode" parameter value is not valid.
     */
    public function testUnknownModesTriggerException()
    {
        new Email(array('mode' => 'Unknown Mode'));
    }
}
