<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\Tests\Form;

use Symfony\Bridge\Propel1\Form\PropelTypeGuesser;
use Symfony\Bridge\Propel1\Tests\Propel1TestCase;

class PropelTypeGuesserTest extends Propel1TestCase
{
    const CLASS_NAME = 'Symfony\Bridge\Propel1\Tests\Fixtures\Item';

    private $guesser;

    public function setUp()
    {
        $this->guesser = new PropelTypeGuesser();
    }

    public function testGuessMaxLengthWithText()
    {
        $value = $this->guesser->guessMaxLength(self::CLASS_NAME, 'value');

        $this->assertNotNull($value);
        $this->assertEquals(255, $value->getValue());
    }

    public function testGuessMaxLengthWithFloat()
    {
        $value = $this->guesser->guessMaxLength(self::CLASS_NAME, 'price');

        $this->assertNotNull($value);
        $this->assertNull($value->getValue());
    }

    public function testGuessMinLengthWithText()
    {
        $value = $this->guesser->guessMinLength(self::CLASS_NAME, 'value');

        $this->assertNull($value);
    }

    public function testGuessMinLengthWithFloat()
    {
        $value = $this->guesser->guessMinLength(self::CLASS_NAME, 'price');

        $this->assertNotNull($value);
        $this->assertNull($value->getValue());
    }

    public function testGuessRequired()
    {
        $value = $this->guesser->guessRequired(self::CLASS_NAME, 'id');

        $this->assertNotNull($value);
        $this->assertTrue($value->getValue());
    }

    public function testGuessRequiredWithNullableColumn()
    {
        $value = $this->guesser->guessRequired(self::CLASS_NAME, 'value');

        $this->assertNotNull($value);
        $this->assertFalse($value->getValue());
    }
}
