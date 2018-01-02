<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Email;

class EmailTest extends TestCase
{
    /**
     * @expectedDeprecation The "strict" property is deprecated since Symfony 4.1 and will be removed in 5.0. Use "mode"=>"strict" instead.
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
