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
use Symfony\Component\Form\Guess\Guess;

class PropelTypeGuesserTest extends Propel1TestCase
{
    const CLASS_NAME = 'Symfony\Bridge\Propel1\Tests\Fixtures\Item';
    const UNKNOWN_CLASS_NAME = 'Symfony\Bridge\Propel1\Tests\Fixtures\UnknownItem';

    private $guesser;

    protected function setUp()
    {
        $this->guesser = new PropelTypeGuesser();
    }

    protected function tearDown()
    {
        $this->guesser = null;
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
        $value = $this->guesser->guessPattern(self::CLASS_NAME, 'value');

        $this->assertNull($value);
    }

    public function testGuessMinLengthWithFloat()
    {
        $value = $this->guesser->guessPattern(self::CLASS_NAME, 'price');

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

    public function testGuessTypeWithoutTable()
    {
        $value = $this->guesser->guessType(self::UNKNOWN_CLASS_NAME, 'property');

        $this->assertNotNull($value);
        $this->assertEquals('text', $value->getType());
        $this->assertEquals(Guess::LOW_CONFIDENCE, $value->getConfidence());
    }

    public function testGuessTypeWithoutColumn()
    {
        $value = $this->guesser->guessType(self::CLASS_NAME, 'property');

        $this->assertNotNull($value);
        $this->assertEquals('text', $value->getType());
        $this->assertEquals(Guess::LOW_CONFIDENCE, $value->getConfidence());
    }

    /**
     * @dataProvider dataProviderForGuessType
     */
    public function testGuessType($property, $type, $confidence, $multiple = null)
    {
        $value = $this->guesser->guessType(self::CLASS_NAME, $property);

        $this->assertNotNull($value);
        $this->assertEquals($type, $value->getType());
        $this->assertEquals($confidence, $value->getConfidence());

        if ($type === 'model') {
            $options = $value->getOptions();

            $this->assertSame($multiple, $options['multiple']);
        }
    }

    public static function dataProviderForGuessType()
    {
        return array(
            array('is_active',  'checkbox', Guess::HIGH_CONFIDENCE),
            array('enabled',    'checkbox', Guess::HIGH_CONFIDENCE),
            array('id',         'integer',  Guess::MEDIUM_CONFIDENCE),
            array('value',      'text',     Guess::MEDIUM_CONFIDENCE),
            array('price',      'number',   Guess::MEDIUM_CONFIDENCE),
            array('updated_at', 'datetime', Guess::HIGH_CONFIDENCE),

            array('isActive',   'checkbox', Guess::HIGH_CONFIDENCE),
            array('updatedAt',  'datetime', Guess::HIGH_CONFIDENCE),

            array('Authors',    'model',    Guess::HIGH_CONFIDENCE,     true),
            array('Resellers',  'model',    Guess::HIGH_CONFIDENCE,     true),
            array('MainAuthor', 'model',    Guess::HIGH_CONFIDENCE,     false),
        );
    }
}
