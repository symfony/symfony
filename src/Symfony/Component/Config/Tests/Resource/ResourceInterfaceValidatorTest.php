<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Resource;

use Symfony\Component\Config\Resource\ResourceInterfaceValidator;

class ResourceInterfaceValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;
    protected $metadataMock;
    protected $timestamp;

    public function setUp()
    {
        $this->timestamp = 10;
        $this->validator = new ResourceInterfaceValidator();
        $this->metadataMock = $this->getMock('\Symfony\Component\Config\Resource\ResourceInterface');
    }

    public function testSupportsResourceInterface()
    {
        $this->assertTrue($this->validator->supports($this->metadataMock), '->supports($metadata) returns true if $metadata implements ResourceInterface');
        $this->assertFalse($this->validator->supports(new \StdClass()), '->supports($metadata) returns false if $metadata does not implement ResourceInterface');
    }

    /**
     * @dataProvider testIsFreshDataProvider
     */
    public function testIsFresh($result)
    {
        $this->metadataMock
            ->expects($this->once())
            ->method('isFresh')
            ->with($this->equalTo($this->timestamp))
            ->will($this->returnValue($result));
        $this->assertEquals($result, $this->validator->isFresh($this->metadataMock, $this->timestamp), '->isFresh($metadata) returns the same as $metadata->isFresh().');
    }

    public function testIsFreshDataProvider()
    {
        return array(
            array(true),
            array(false),
        );
    }

}
