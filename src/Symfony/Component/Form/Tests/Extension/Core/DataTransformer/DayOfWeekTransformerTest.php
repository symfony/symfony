<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataTransformer;

use Symfony\Component\Form\Extension\Core\DataTransformer\DayOfWeekTransformer;

class DayOfWeekTransformerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var DayOfWeekTransformer
     */
    private $transformer;

    /**
     * @var string
     */
    private $oldLocale;

    protected function setUp()
    {
        $this->transformer = new DayOfWeekTransformer();
        $this->oldLocale = \Locale::getDefault();
    }

    protected function tearDown()
    {
        $this->transformer = null;
        \Locale::setDefault($this->oldLocale);
    }

    /**
     * @dataProvider getValidData
     */
    public function testTransforms($locale, $pattern, $in, $out)
    {
        \Locale::setDefault($locale);
        $transformer = new DayOfWeekTransformer($pattern);

        $this->assertEquals($out, $transformer->transform($in));
    }

    /**
     * @dataProvider getValidData
     */
    public function testReverseTransforms($locale, $pattern, $in, $out)
    {
        \Locale::setDefault($locale);
        $transformer = new DayOfWeekTransformer($pattern);

        $this->assertEquals($in, $transformer->reverseTransform($out));
    }

    public function getValidData()
    {
        return array(
            array('fr_FR', 'eeee', 7, 'dimanche'),
            array('en_US', 'eeee', 7, 'Sunday'),

            array('fr_FR', 'e', 1, 1), // locale dependent
            array('en_US', 'e', 1, 2),

            array('en_US', 'eee', 6, 'Sat'),
            array('en_US', 'eee', '6', 'Sat'), // string int is ok
        );
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testInvalidPattern()
    {
        new DayOfWeekTransformer('w');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     * @dataProvider getInvalidTransforms
     */
    public function testInvalidTransforms($in)
    {
        $this->transformer->transform($in);
    }

    public function getInvalidTransforms()
    {
        return array(
            array(''),
            array('Monday'),
            array(0),
            array(8),
            array(null),
            array(new \stdClass()),
            array(array()),
        );
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     * @dataProvider getInvalidReverseTransforms
     */
    public function testInvalidReverseTransforms($in)
    {
        $this->transformer->reverseTransform($in);
    }

    public function getInvalidReverseTransforms()
    {
        return array(
            array(1),
            array(''),
            array(null),
            array(new \stdClass()),
            array(array()),
        );
    }
}
