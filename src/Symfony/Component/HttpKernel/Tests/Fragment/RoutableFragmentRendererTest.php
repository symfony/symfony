<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fragment;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerReference;

class RoutableFragmentRendererTest extends TestCase
{
    /**
     * @dataProvider getGenerateFragmentUriData
     */
    public function testGenerateFragmentUri($uri, $controller)
    {
        $this->assertEquals($uri, $this->callGenerateFragmentUriMethod($controller, Request::create('/')));
    }

    /**
     * @dataProvider getGenerateFragmentUriData
     */
    public function testGenerateAbsoluteFragmentUri($uri, $controller)
    {
        $this->assertEquals('http://localhost'.$uri, $this->callGenerateFragmentUriMethod($controller, Request::create('/'), true));
    }

    public function getGenerateFragmentUriData()
    {
        return [
            ['/_fragment?_path=_format%3Dhtml%26_locale%3Den%26_controller%3Dcontroller', new ControllerReference('controller', [], [])],
            ['/_fragment?_path=_format%3Dxml%26_locale%3Den%26_controller%3Dcontroller', new ControllerReference('controller', ['_format' => 'xml'], [])],
            ['/_fragment?_path=foo%3Dfoo%26_format%3Djson%26_locale%3Den%26_controller%3Dcontroller', new ControllerReference('controller', ['foo' => 'foo', '_format' => 'json'], [])],
            ['/_fragment?bar=bar&_path=foo%3Dfoo%26_format%3Dhtml%26_locale%3Den%26_controller%3Dcontroller', new ControllerReference('controller', ['foo' => 'foo'], ['bar' => 'bar'])],
            ['/_fragment?foo=foo&_path=_format%3Dhtml%26_locale%3Den%26_controller%3Dcontroller', new ControllerReference('controller', [], ['foo' => 'foo'])],
            ['/_fragment?_path=foo%255B0%255D%3Dfoo%26foo%255B1%255D%3Dbar%26_format%3Dhtml%26_locale%3Den%26_controller%3Dcontroller', new ControllerReference('controller', ['foo' => ['foo', 'bar']], [])],
        ];
    }

    public function testGenerateFragmentUriWithARequest()
    {
        $request = Request::create('/');
        $request->attributes->set('_format', 'json');
        $request->setLocale('fr');
        $controller = new ControllerReference('controller', [], []);

        $this->assertEquals('/_fragment?_path=_format%3Djson%26_locale%3Dfr%26_controller%3Dcontroller', $this->callGenerateFragmentUriMethod($controller, $request));
    }

    /**
     * @dataProvider getGenerateFragmentUriDataWithNonScalar
     */
    public function testGenerateFragmentUriWithNonScalar($controller)
    {
        $this->expectException('LogicException');
        $this->callGenerateFragmentUriMethod($controller, Request::create('/'));
    }

    public function getGenerateFragmentUriDataWithNonScalar()
    {
        return [
            [new ControllerReference('controller', ['foo' => new Foo(), 'bar' => 'bar'], [])],
            [new ControllerReference('controller', ['foo' => ['foo' => 'foo'], 'bar' => ['bar' => new Foo()]], [])],
        ];
    }

    private function callGenerateFragmentUriMethod(ControllerReference $reference, Request $request, $absolute = false)
    {
        $renderer = $this->getMockForAbstractClass('Symfony\Component\HttpKernel\Fragment\RoutableFragmentRenderer');
        $r = new \ReflectionObject($renderer);
        $m = $r->getMethod('generateFragmentUri');
        $m->setAccessible(true);

        return $m->invoke($renderer, $reference, $request, $absolute);
    }
}

class Foo
{
    public $foo;

    public function getFoo()
    {
        return $this->foo;
    }
}
