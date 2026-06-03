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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\CompiledRoute;

class CompiledRouteTestToStringGadget
{
    public static bool $fired = false;

    public function __toString(): string
    {
        self::$fired = true;

        return '';
    }
}

class CompiledRouteTest extends TestCase
{
    public function testAccessors()
    {
        $compiled = new CompiledRoute('prefix', 'regex', ['tokens'], [], null, [], [], ['variables']);
        $this->assertEquals('prefix', $compiled->getStaticPrefix(), '__construct() takes a static prefix as its second argument');
        $this->assertEquals('regex', $compiled->getRegex(), '__construct() takes a regexp as its third argument');
        $this->assertEquals(['tokens'], $compiled->getTokens(), '__construct() takes an array of tokens as its fourth argument');
        $this->assertEquals(['variables'], $compiled->getVariables(), '__construct() takes an array of variables as its ninth argument');
    }

    #[DataProvider('provideTrampolineProperties')]
    public function testUnserializeRejectsObjectInTypedScalarProperty(string $property)
    {
        $data = [
            'vars' => [],
            'path_prefix' => '',
            'path_regex' => '',
            'path_tokens' => [],
            'path_vars' => [],
            'host_regex' => null,
            'host_tokens' => [],
            'host_vars' => [],
        ];
        $data[$property] = new CompiledRouteTestToStringGadget();
        $payload = \sprintf('O:%d:"%s":%d:{', \strlen(CompiledRoute::class), CompiledRoute::class, \count($data));
        foreach ($data as $key => $value) {
            $payload .= serialize($key).serialize($value);
        }
        $payload .= '}';
        CompiledRouteTestToStringGadget::$fired = false;

        try {
            unserialize($payload);
            $this->fail('Expected BadMethodCallException.');
        } catch (\BadMethodCallException $e) {
        }

        $this->assertFalse(CompiledRouteTestToStringGadget::$fired, '__toString gadget must not fire during unserialize');
    }

    public static function provideTrampolineProperties(): iterable
    {
        yield ['path_prefix'];
        yield ['path_regex'];
        yield ['host_regex'];
    }
}
