<?php

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\Size;

class SizeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getMinMessageData
     */
    public function testGetMinMessage($options, $type, $expected)
    {
        $size = new Size($options);
        $this->assertEquals($expected, $size->getMinMessage($type));
    }

    public function getMinMessageData()
    {
        $size = new Size();

        return array(
            array(array(), Size::TYPE_STRING, $this->readAttribute($size, 'stringMinMessage')),
            array(array(), Size::TYPE_COLLECTION, $this->readAttribute($size, 'collectionMinMessage')),
            array(array('minMessage' => 'Custom min message'), Size::TYPE_STRING, 'Custom min message'),
            array(array('minMessage' => 'Custom min message'), Size::TYPE_COLLECTION, 'Custom min message'),
        );
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testGetMinMessageWithInvalidType()
    {
        $size = new Size();
        $size->getMinMessage('foo');
    }

    /**
     * @dataProvider getMaxMessageData
     */
    public function testGetMaxMessage($options, $type, $expected)
    {
        $size = new Size($options);
        $this->assertEquals($expected, $size->getMaxMessage($type));
    }

    public function getMaxMessageData()
    {
        $size = new Size();

        return array(
            array(array(), Size::TYPE_STRING, $this->readAttribute($size, 'stringMaxMessage')),
            array(array(), Size::TYPE_COLLECTION, $this->readAttribute($size, 'collectionMaxMessage')),
            array(array('maxMessage' => 'Custom max message'), Size::TYPE_STRING, 'Custom max message'),
            array(array('maxMessage' => 'Custom max message'), Size::TYPE_COLLECTION, 'Custom max message'),
        );
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testGetMaxMessageWithInvalidType()
    {
        $size = new Size();
        $size->getMaxMessage('foo');
    }

    /**
     * @dataProvider getExactMessageData
     */
    public function testGetExactMessage($options, $type, $expected)
    {
        $size = new Size($options);
        $this->assertEquals($expected, $size->getExactMessage($type));
    }

    public function getExactMessageData()
    {
        $size = new Size();

        return array(
            array(array(), Size::TYPE_STRING, $this->readAttribute($size, 'stringExactMessage')),
            array(array(), Size::TYPE_COLLECTION, $this->readAttribute($size, 'collectionExactMessage')),
            array(array('exactMessage' => 'Custom exact message'), Size::TYPE_STRING, 'Custom exact message'),
            array(array('exactMessage' => 'Custom exact message'), Size::TYPE_COLLECTION, 'Custom exact message'),
        );
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testGetExactMessageWithInvalidType()
    {
        $size = new Size();
        $size->getExactMessage('foo');
    }
}
