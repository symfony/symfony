<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCompiler;

class RouteCompilerTest extends TestCase
{
    /**
     * @dataProvider provideCompileData
     */
    public function testCompile($name, $arguments, $prefix, $regex, $variables, $tokens)
    {
        $r = new \ReflectionClass('Symfony\\Component\\Routing\\Route');
        $route = $r->newInstanceArgs($arguments);

        $compiled = $route->compile();
        $this->assertEquals($prefix, $compiled->getStaticPrefix(), $name.' (static prefix)');
        $this->assertEquals($regex, $compiled->getRegex(), $name.' (regex)');
        $this->assertEquals($variables, $compiled->getVariables(), $name.' (variables)');
        $this->assertEquals($tokens, $compiled->getTokens(), $name.' (tokens)');
    }

    public function provideCompileData()
    {
        return [
            [
                'Static route',
                ['/foo'],
                '/foo', '#^/foo$#sD', [], [
                    ['text', '/foo'],
                ],
            ],

            [
                'Route with a variable',
                ['/foo/{bar}'],
                '/foo', '#^/foo/(?P<bar>[^/]++)$#sD', ['bar'], [
                    ['variable', '/', '[^/]++', 'bar'],
                    ['text', '/foo'],
                ],
            ],

            [
                'Route with a variable that has a default value',
                ['/foo/{bar}', ['bar' => 'bar']],
                '/foo', '#^/foo(?:/(?P<bar>[^/]++))?$#sD', ['bar'], [
                    ['variable', '/', '[^/]++', 'bar'],
                    ['text', '/foo'],
                ],
            ],

            [
                'Route with several variables',
                ['/foo/{bar}/{foobar}'],
                '/foo', '#^/foo/(?P<bar>[^/]++)/(?P<foobar>[^/]++)$#sD', ['bar', 'foobar'], [
                    ['variable', '/', '[^/]++', 'foobar'],
                    ['variable', '/', '[^/]++', 'bar'],
                    ['text', '/foo'],
                ],
            ],

            [
                'Route with several variables that have default values',
                ['/foo/{bar}/{foobar}', ['bar' => 'bar', 'foobar' => '']],
                '/foo', '#^/foo(?:/(?P<bar>[^/]++)(?:/(?P<foobar>[^/]++))?)?$#sD', ['bar', 'foobar'], [
                    ['variable', '/', '[^/]++', 'foobar'],
                    ['variable', '/', '[^/]++', 'bar'],
                    ['text', '/foo'],
                ],
            ],

            [
                'Route with several variables but some of them have no default values',
                ['/foo/{bar}/{foobar}', ['bar' => 'bar']],
                '/foo', '#^/foo/(?P<bar>[^/]++)/(?P<foobar>[^/]++)$#sD', ['bar', 'foobar'], [
                    ['variable', '/', '[^/]++', 'foobar'],
                    ['variable', '/', '[^/]++', 'bar'],
                    ['text', '/foo'],
                ],
            ],

            [
                'Route with an optional variable as the first segment',
                ['/{bar}', ['bar' => 'bar']],
                '', '#^/(?P<bar>[^/]++)?$#sD', ['bar'], [
                    ['variable', '/', '[^/]++', 'bar'],
                ],
            ],

            [
                'Route with a requirement of 0',
                ['/{bar}', ['bar' => null], ['bar' => '0']],
                '', '#^/(?P<bar>0)?$#sD', ['bar'], [
                    ['variable', '/', '0', 'bar'],
                ],
            ],

            [
                'Route with an optional variable as the first segment with requirements',
                ['/{bar}', ['bar' => 'bar'], ['bar' => '(foo|bar)']],
                '', '#^/(?P<bar>(foo|bar))?$#sD', ['bar'], [
                    ['variable', '/', '(foo|bar)', 'bar'],
                ],
            ],

            [
                'Route with only optional variables',
                ['/{foo}/{bar}', ['foo' => 'foo', 'bar' => 'bar']],
                '', '#^/(?P<foo>[^/]++)?(?:/(?P<bar>[^/]++))?$#sD', ['foo', 'bar'], [
                    ['variable', '/', '[^/]++', 'bar'],
                    ['variable', '/', '[^/]++', 'foo'],
                ],
            ],

            [
                'Route with a variable in last position',
                ['/foo-{bar}'],
                '/foo-', '#^/foo\-(?P<bar>[^/]++)$#sD', ['bar'], [
                    ['variable', '-', '[^/]++', 'bar'],
                    ['text', '/foo'],
                ],
            ],

            [
                'Route with nested placeholders',
                ['/{static{var}static}'],
                '/{static', '#^/\{static(?P<var>[^/]+)static\}$#sD', ['var'], [
                    ['text', 'static}'],
                    ['variable', '', '[^/]+', 'var'],
                    ['text', '/{static'],
                ],
            ],

            [
                'Route without separator between variables',
                ['/{w}{x}{y}{z}.{_format}', ['z' => 'default-z', '_format' => 'html'], ['y' => '(y|Y)']],
                '', '#^/(?P<w>[^/\.]+)(?P<x>[^/\.]+)(?P<y>(y|Y))(?:(?P<z>[^/\.]++)(?:\.(?P<_format>[^/]++))?)?$#sD', ['w', 'x', 'y', 'z', '_format'], [
                    ['variable', '.', '[^/]++', '_format'],
                    ['variable', '', '[^/\.]++', 'z'],
                    ['variable', '', '(y|Y)', 'y'],
                    ['variable', '', '[^/\.]+', 'x'],
                    ['variable', '/', '[^/\.]+', 'w'],
                ],
            ],

            [
                'Route with a format',
                ['/foo/{bar}.{_format}'],
                '/foo', '#^/foo/(?P<bar>[^/\.]++)\.(?P<_format>[^/]++)$#sD', ['bar', '_format'], [
                    ['variable', '.', '[^/]++', '_format'],
                    ['variable', '/', '[^/\.]++', 'bar'],
                    ['text', '/foo'],
                ],
            ],

            [
                'Static non UTF-8 route',
                ["/fo\xE9"],
                "/fo\xE9", "#^/fo\xE9$#sD", [], [
                    ['text', "/fo\xE9"],
                ],
            ],

            [
                'Route with an explicit UTF-8 requirement',
                ['/{bar}', ['bar' => null], ['bar' => '.'], ['utf8' => true]],
                '', '#^/(?P<bar>.)?$#sDu', ['bar'], [
                    ['variable', '/', '.', 'bar', true],
                ],
            ],
        ];
    }

    /**
     * @group legacy
     * @dataProvider provideCompileImplicitUtf8Data
     * @expectedDeprecation Using UTF-8 route %s without setting the "utf8" option is deprecated %s.
     */
    public function testCompileImplicitUtf8Data($name, $arguments, $prefix, $regex, $variables, $tokens, $deprecationType)
    {
        $r = new \ReflectionClass('Symfony\\Component\\Routing\\Route');
        $route = $r->newInstanceArgs($arguments);

        $compiled = $route->compile();
        $this->assertEquals($prefix, $compiled->getStaticPrefix(), $name.' (static prefix)');
        $this->assertEquals($regex, $compiled->getRegex(), $name.' (regex)');
        $this->assertEquals($variables, $compiled->getVariables(), $name.' (variables)');
        $this->assertEquals($tokens, $compiled->getTokens(), $name.' (tokens)');
    }

    public function provideCompileImplicitUtf8Data()
    {
        return [
            [
                'Static UTF-8 route',
                ['/foé'],
                '/foé', '#^/foé$#sDu', [], [
                    ['text', '/foé'],
                ],
                'patterns',
            ],

            [
                'Route with an implicit UTF-8 requirement',
                ['/{bar}', ['bar' => null], ['bar' => 'é']],
                '', '#^/(?P<bar>é)?$#sDu', ['bar'], [
                    ['variable', '/', 'é', 'bar', true],
                ],
                'requirements',
            ],

            [
                'Route with a UTF-8 class requirement',
                ['/{bar}', ['bar' => null], ['bar' => '\pM']],
                '', '#^/(?P<bar>\pM)?$#sDu', ['bar'], [
                    ['variable', '/', '\pM', 'bar', true],
                ],
                'requirements',
            ],

            [
                'Route with a UTF-8 separator',
                ['/foo/{bar}§{_format}', [], [], ['compiler_class' => Utf8RouteCompiler::class]],
                '/foo', '#^/foo/(?P<bar>[^/§]++)§(?P<_format>[^/]++)$#sDu', ['bar', '_format'], [
                    ['variable', '§', '[^/]++', '_format', true],
                    ['variable', '/', '[^/§]++', 'bar', true],
                    ['text', '/foo'],
                ],
                'patterns',
            ],
        ];
    }

    /**
     * @expectedException \LogicException
     */
    public function testRouteWithSameVariableTwice()
    {
        $route = new Route('/{name}/{name}');

        $compiled = $route->compile();
    }

    /**
     * @expectedException \LogicException
     */
    public function testRouteCharsetMismatch()
    {
        $route = new Route("/\xE9/{bar}", [], ['bar' => '.'], ['utf8' => true]);

        $compiled = $route->compile();
    }

    /**
     * @expectedException \LogicException
     */
    public function testRequirementCharsetMismatch()
    {
        $route = new Route('/foo/{bar}', [], ['bar' => "\xE9"], ['utf8' => true]);

        $compiled = $route->compile();
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRouteWithFragmentAsPathParameter()
    {
        $route = new Route('/{_fragment}');

        $compiled = $route->compile();
    }

    /**
     * @dataProvider getVariableNamesStartingWithADigit
     * @expectedException \DomainException
     */
    public function testRouteWithVariableNameStartingWithADigit($name)
    {
        $route = new Route('/{'.$name.'}');
        $route->compile();
    }

    public function getVariableNamesStartingWithADigit()
    {
        return [
           ['09'],
           ['123'],
           ['1e2'],
        ];
    }

    /**
     * @dataProvider provideCompileWithHostData
     */
    public function testCompileWithHost($name, $arguments, $prefix, $regex, $variables, $pathVariables, $tokens, $hostRegex, $hostVariables, $hostTokens)
    {
        $r = new \ReflectionClass('Symfony\\Component\\Routing\\Route');
        $route = $r->newInstanceArgs($arguments);

        $compiled = $route->compile();
        $this->assertEquals($prefix, $compiled->getStaticPrefix(), $name.' (static prefix)');
        $this->assertEquals($regex, str_replace(["\n", ' '], '', $compiled->getRegex()), $name.' (regex)');
        $this->assertEquals($variables, $compiled->getVariables(), $name.' (variables)');
        $this->assertEquals($pathVariables, $compiled->getPathVariables(), $name.' (path variables)');
        $this->assertEquals($tokens, $compiled->getTokens(), $name.' (tokens)');
        $this->assertEquals($hostRegex, str_replace(["\n", ' '], '', $compiled->getHostRegex()), $name.' (host regex)');
        $this->assertEquals($hostVariables, $compiled->getHostVariables(), $name.' (host variables)');
        $this->assertEquals($hostTokens, $compiled->getHostTokens(), $name.' (host tokens)');
    }

    public function provideCompileWithHostData()
    {
        return [
            [
                'Route with host pattern',
                ['/hello', [], [], [], 'www.example.com'],
                '/hello', '#^/hello$#sD', [], [], [
                    ['text', '/hello'],
                ],
                '#^www\.example\.com$#sDi', [], [
                    ['text', 'www.example.com'],
                ],
            ],
            [
                'Route with host pattern and some variables',
                ['/hello/{name}', [], [], [], 'www.example.{tld}'],
                '/hello', '#^/hello/(?P<name>[^/]++)$#sD', ['tld', 'name'], ['name'], [
                    ['variable', '/', '[^/]++', 'name'],
                    ['text', '/hello'],
                ],
                '#^www\.example\.(?P<tld>[^\.]++)$#sDi', ['tld'], [
                    ['variable', '.', '[^\.]++', 'tld'],
                    ['text', 'www.example'],
                ],
            ],
            [
                'Route with variable at beginning of host',
                ['/hello', [], [], [], '{locale}.example.{tld}'],
                '/hello', '#^/hello$#sD', ['locale', 'tld'], [], [
                    ['text', '/hello'],
                ],
                '#^(?P<locale>[^\.]++)\.example\.(?P<tld>[^\.]++)$#sDi', ['locale', 'tld'], [
                    ['variable', '.', '[^\.]++', 'tld'],
                    ['text', '.example'],
                    ['variable', '', '[^\.]++', 'locale'],
                ],
            ],
            [
                'Route with host variables that has a default value',
                ['/hello', ['locale' => 'a', 'tld' => 'b'], [], [], '{locale}.example.{tld}'],
                '/hello', '#^/hello$#sD', ['locale', 'tld'], [], [
                    ['text', '/hello'],
                ],
                '#^(?P<locale>[^\.]++)\.example\.(?P<tld>[^\.]++)$#sDi', ['locale', 'tld'], [
                    ['variable', '.', '[^\.]++', 'tld'],
                    ['text', '.example'],
                    ['variable', '', '[^\.]++', 'locale'],
                ],
            ],
        ];
    }

    /**
     * @expectedException \DomainException
     */
    public function testRouteWithTooLongVariableName()
    {
        $route = new Route(sprintf('/{%s}', str_repeat('a', RouteCompiler::VARIABLE_MAXIMUM_LENGTH + 1)));
        $route->compile();
    }
}

class Utf8RouteCompiler extends RouteCompiler
{
    const SEPARATORS = '/§';
}
