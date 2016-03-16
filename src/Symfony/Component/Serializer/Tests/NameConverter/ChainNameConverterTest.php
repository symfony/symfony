<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\NameConverter;

use Symfony\Component\Serializer\NameConverter\ChainNameConverter;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * @author Jérôme Gamez <jerome@gamez.name>
 */
class ChainNameConverterTest extends \PHPUnit_Framework_TestCase
{
    protected $chainConverter;

    protected $firstConverter;
    protected $secondConverter;

    protected function setUp()
    {
        $this->firstConverter = $this->getMock(NameConverterInterface::class);
        $this->secondConverter = $this->getMock(NameConverterInterface::class);

        $this->chainConverter = new ChainNameConverter(array($this->firstConverter, $this->secondConverter));
    }

    public function testNormalize()
    {
        $this->firstConverter->expects($this->once())->method('normalize')->with('one')->willReturn('two');
        $this->secondConverter->expects($this->once())->method('normalize')->with('two')->willReturn('three');

        $this->assertEquals('three', $this->chainConverter->normalize('one'));
    }

    public function testDenormalize()
    {
        $this->firstConverter->expects($this->once())->method('denormalize')->with('three')->willReturn('two');
        $this->secondConverter->expects($this->once())->method('denormalize')->with('two')->willReturn('one');

        $this->assertEquals('one', $this->chainConverter->denormalize('three'));
    }
}
