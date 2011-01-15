<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Request\ParamConverter;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Request\ParamConverter\ConverterManager;

class ConverterManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testManagerCanContainerConverters()
    {
        $manager = new ConverterManager();
        $importantConverter = $this->getConverterInterfaceMock();
        $lessImportantConverter = $this->getConverterInterfaceMock();

        $manager->add($importantConverter, 10);

        $this->assertEquals($manager->all(), array($importantConverter));

        $manager->add($lessImportantConverter);

        $this->assertEquals($manager->all(), array(
            $importantConverter,
            $lessImportantConverter,
        ));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testManagerCantApplyConvertersAndThrowsException()
    {
        $request = new Request();
        $parameter = $this->getReflectionParameter();

        $converter = $this->getConverterInterfaceMock();
        $converter->expects($this->once())
                  ->method('supports')
                  ->with($parameter->getClass())
                  ->will($this->returnValue(false));

        $manager = new ConverterManager();
        $manager->add($converter);
        $manager->apply($request, $parameter);
    }

    public function testManagerWillApplyConvertersSuccessfully()
    {
        $request = new Request();
        $parameter = $this->getReflectionParameter();

        $converter = $this->getConverterInterfaceMock();
        $converter->expects($this->once())
                  ->method('supports')
                  ->with($parameter->getClass())
                  ->will($this->returnValue(true));

        $converter->expects($this->once())
                  ->method('apply')
                  ->with($request, $parameter)
                  ->will($this->returnValue(null));

        $manager = new ConverterManager();
        $manager->add($converter);
        $manager->apply($request, $parameter);
    }

    private function getReflectionParameter()
    {
        return new \ReflectionParameter(array('Symfony\Bundle\FrameworkBundle\Tests\Request\ParamConverter\Fixtures\ConvertableObject', 'typehintedMethod'), 'object');
    }

    private function getConverterInterfaceMock()
    {
        return $this->getMock('Symfony\Bundle\FrameworkBundle\Request\ParamConverter\ConverterInterface');
    }
}
