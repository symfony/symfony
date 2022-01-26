<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Controller\ParamConverter;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute;
use Symfony\Component\HttpKernel\Controller\ParamConverter\ParamConverterManager;

class ParamConverterManagerTest extends \PHPUnit\Framework\TestCase
{
    public function testPriorities()
    {
        $manager = new ParamConverterManager();
        $this->assertEquals([], $manager->all());

        $high = $this->createParamConverterMock();
        $low = $this->createParamConverterMock();

        $manager->add($low);
        $manager->add($high, 10);

        $this->assertEquals([$high, $low], $manager->all());
    }

    public function testApply()
    {
        $supported = $this->createParamConverterMock();
        $supported
            ->expects($this->once())
            ->method('supports')
            ->willReturn(true)
        ;
        $supported
            ->expects($this->once())
            ->method('apply')
            ->willReturn(false)
        ;

        $invalid = $this->createParamConverterMock();
        $invalid
            ->expects($this->once())
            ->method('supports')
            ->willReturn(false)
        ;
        $invalid
            ->expects($this->never())
            ->method('apply')
        ;

        $configurations = [
            new Attribute\ParamConverter('var'),
        ];

        $manager = new ParamConverterManager();
        $manager->add($supported);
        $manager->add($invalid);
        $manager->apply(new Request(), $configurations);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testApplyNamedConverter()
    {
        $converter = $this->createParamConverterMock();
        $converter
            ->expects($this->any())
            ->method('supports')
            ->willReturn(true)
        ;

        $converter
            ->expects($this->any())
            ->method('apply')
        ;

        $request = new Request();
        $request->attributes->set('param', '1234');

        $configuration = new Attribute\ParamConverter('param', 'stdClass', [], false, 'test');

        $manager = new ParamConverterManager();
        $manager->add($converter, 0, 'test');
        $manager->apply($request, [$configuration]);
    }

    public function testApplyNamedConverterNotSupportsParameter()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Converter \'test\' does not support conversion of parameter \'param\'.');

        $converter = $this->createParamConverterMock();
        $converter
            ->expects($this->any())
            ->method('supports')
            ->willReturn(false)
        ;

        $request = new Request();
        $request->attributes->set('param', '1234');

        $configuration = new Attribute\ParamConverter(name: 'param', class: 'stdClass', converter: 'test');

        $manager = new ParamConverterManager();
        $manager->add($converter, 0, 'test');
        $manager->apply($request, [$configuration]);
    }

    public function testApplyNamedConverterNoConverter()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No converter named \'test\' found for conversion of parameter \'param\'.');

        $request = new Request();
        $request->attributes->set('param', '1234');

        $configuration = new Attribute\ParamConverter(name: 'param', class: 'stdClass', converter: 'test');

        $manager = new ParamConverterManager();
        $manager->apply($request, [$configuration]);
    }

    public function testApplyNotCalledOnAlreadyConvertedObjects()
    {
        $converter = $this->createParamConverterMock();
        $converter
            ->expects($this->never())
            ->method('supports')
        ;

        $converter
            ->expects($this->never())
            ->method('apply')
        ;

        $request = new Request();
        $request->attributes->set('converted', new \stdClass());

        $configuration = new Attribute\ParamConverter(name: 'converted', class: 'stdClass');

        $manager = new ParamConverterManager();
        $manager->add($converter);
        $manager->apply($request, [$configuration]);
    }

    private function createParamConverterMock()
    {
        return $this->getMockBuilder('Symfony\Component\HttpKernel\Controller\ParamConverter\ParamConverterInterface')->getMock();
    }
}
