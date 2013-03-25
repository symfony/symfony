<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating;

use PHPUnit_Framework_Assert;

use Symfony\Bundle\FrameworkBundle\Templating\ProxyEngine;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Templating\TemplateNameParser;

class ProxyEngineTest extends TestCase
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var ProxyEngine
     */
    protected $templating;

    /**
     * @var string
     */
    protected $engine = 'foo';

    /**
     * @var string
     */
    protected $target = 'bar';

    /**
     * @version 0.0.1
     * @since 0.0.1
     */
    protected function setUp()
    {
        $this->container = new Container();
        $this->templating = new ProxyEngine($this->container, new TemplateNameParser(), $this->engine, $this->target);
    }

    /**
     * @return Symfony\Bundle\FrameworkBundle\Templating\EngineInterface
     */
    protected function createTemplating($class)
    {
        $templating = $this->getMock($class);
        $this->container->set('templating', $templating);
        return $templating;
    }

    public function testRenderSubsequentEngine()
    {
        $parameters = array('baz' => 'qux');

        // for closure scope
        $target = $this->target;
        $toReturn = new \stdClass();

        $templating = $this->createTemplating('Symfony\\Bundle\\FrameworkBundle\\Templating\\EngineInterface');
        $templating->expects($this->once())
            ->method('render')
            ->with(
                $this->anything(),
                $this->equalTo($parameters)
            )
            ->will($this->returnCallback(function($name) use ($target, $toReturn) {
                PHPUnit_Framework_Assert::assertEquals($target, $name->get('engine'), 'ProxyEngine::render() should call sub-sequent engine render() method on template with same name but engine replaced to configured one.');
                return $toReturn;
            }));

        $return = $this->templating->render('quux.' . $this->engine, $parameters);
        $this->assertSame($toReturn, $return, 'ProxyEngine::render() should return result of sub-sequent engine render() method.');
    }

    public function testRenderDefaultParameters()
    {
        // for closure scope
        $target = $this->target;
        $toReturn = new \stdClass();

        $templating = $this->createTemplating('Symfony\\Bundle\\FrameworkBundle\\Templating\\EngineInterface');
        $templating->expects($this->once())
            ->method('render')
            ->with(
                $this->anything(),
                $this->isEmpty()
            );

        $this->templating->render('quux.' . $this->engine);
    }

    public function testExistsSubsequentEngine()
    {
        // for closure scope
        $target = $this->target;
        $toReturn = new \stdClass();

        $templating = $this->createTemplating('Symfony\\Bundle\\FrameworkBundle\\Templating\\EngineInterface');
        $templating->expects($this->once())
            ->method('exists')
            ->with(
                $this->anything()
            )
            ->will($this->returnCallback(function($name) use ($target, $toReturn) {
                PHPUnit_Framework_Assert::assertEquals($target, $name->get('engine'), 'ProxyEngine::exists() should call sub-sequent engine exists() method on template with same name but engine replaced to configured one.');
                return $toReturn;
            }));

        $return = $this->templating->exists('quux.' . $this->engine);
        $this->assertSame($toReturn, $return, 'ProxyEngine::exists() should return result of sub-sequent engine exists() method.');
    }

    public function testSupportsProxyEngine()
    {
        $this->assertTrue($this->templating->supports('quux.' . $this->engine), 'ProxyEngine::supports() should handle templates of defined engine.');
        $this->assertFalse($this->templating->supports('quux.twig'), 'ProxyEngine::supports() should not handle templates other then defined engine.');
    }

    public function testRenderResponseSubsequentEngine()
    {
        $parameters = array('baz' => 'qux');

        // for closure scope
        $target = $this->target;
        $response = new Response();

        $templating = $this->createTemplating('Symfony\\Bundle\\FrameworkBundle\\Templating\\EngineInterface');
        $templating->expects($this->once())
            ->method('renderResponse')
            ->with(
                $this->anything(),
                $this->equalTo($parameters),
                $this->identicalTo($response)
            )
            ->will($this->returnCallback(function($name, array $parameters, Response $response) use ($target) {
                PHPUnit_Framework_Assert::assertEquals($target, $name->get('engine'), 'ProxyEngine::renderResponse() should call sub-sequent engine renderResponse() method on template with same name but engine replaced to configured one.');
                return $response;
            }));

        $return = $this->templating->renderResponse('quux.' . $this->engine, $parameters, $response);
        $this->assertSame($response, $return, 'ProxyEngine::renderResponse() should return result of sub-sequent engine renderResponse() method.');
    }

    public function testRenderResponseDefaultParameters()
    {
        // for closure scope
        $target = $this->target;
        $toReturn = new \stdClass();

        $templating = $this->createTemplating('Symfony\\Bundle\\FrameworkBundle\\Templating\\EngineInterface');
        $templating->expects($this->once())
            ->method('renderResponse')
            ->with(
                $this->anything(),
                $this->isEmpty(),
                $this->isNull()
            )
            ->will($this->returnValue(new Response()));

        $return = $this->templating->renderResponse('quux.' . $this->engine);
        $this->assertInstanceOf('Symfony\\Component\\HttpFoundation\\Response', $return, 'ProxyEngine::renderResponse() should always return instance of type Symfony\\Component\\HttpFoundation\\Response.');
    }

    public function testStreamWithoutSupport()
    {
        $templating = $this->createTemplating('Symfony\\Bundle\\FrameworkBundle\\Templating\\EngineInterface');
        // cann't use @expectedExceptionMessage since class name vary
        $this->setExpectedException('LogicException', 'Template "quux.foo" cannot be streamed as the sub-sequent target engine "' . \get_class($templating) . '" configured to handle it does not implement StreamingEngineInterface.');

        $this->templating->stream('quux.' . $this->engine);
    }

    public function testStreamSubsequentEngine()
    {
        $parameters = array('baz' => 'qux');

        // for closure scope
        $target = $this->target;

        $templating = $this->createTemplating('Symfony\\Component\\Templating\\StreamingEngineInterface');
        $templating->expects($this->once())
            ->method('stream')
            ->with(
                $this->anything(),
                $this->equalTo($parameters)
            )
            ->will($this->returnCallback(function($name, array $parameters) use ($target) {
                PHPUnit_Framework_Assert::assertEquals($target, $name->get('engine'), 'ProxyEngine::stream() should call sub-sequent engine stream() method on template with same name but engine replaced to configured one.');
            }));

        $this->templating->stream('quux.' . $this->engine, $parameters);
    }

    public function testStreamDefaultParameters()
    {
        // for closure scope
        $target = $this->target;

        $templating = $this->createTemplating('Symfony\\Component\\Templating\\StreamingEngineInterface');
        $templating->expects($this->once())
            ->method('stream')
            ->with(
                $this->anything(),
                $this->isEmpty()
            )
            ->will($this->returnCallback(function($name, array $parameters) use ($target) {
                PHPUnit_Framework_Assert::assertEquals($target, $name->get('engine'), 'ProxyEngine::stream() should call sub-sequent engine stream() method on template with same name but engine replaced to configured one.');
            }));

        $this->templating->stream('quux.' . $this->engine);
    }
}
