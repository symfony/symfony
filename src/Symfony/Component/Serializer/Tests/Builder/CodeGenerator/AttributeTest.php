<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Builder\CodeGenerator;

use App\CodeGenerator\_Attribute;
use PHPUnit\Framework\TestCase;

class AttributeTest extends TestCase
{
    public function testNoParameters()
    {
        $output = _Attribute::create('Foobar')->toString();
        $this->assertEquals('#[Foobar]', $output);

        $output = _Attribute::create('Foo\\Bar')->toString();
        $this->assertEquals('#[Foo\\Bar]', $output);
    }

    public function testParameters()
    {
        $output = _Attribute::create('Foobar')
            ->addParameter(null, 7)
            ->toString();
        $this->assertEquals('#[Foobar(7)]', $output);

        $output = _Attribute::create('Foobar')
            ->addParameter(null, 7)
            ->addParameter(null, true)
            ->addParameter(null, false)
            ->addParameter(null, null)
            ->addParameter(null, 47.11)
            ->addParameter(null, 'tobias')
            ->addParameter(null, [47, 'test'])
            ->toString();
        $this->assertEquals('#[Foobar(7, true, false, NULL, 47.11, "tobias", [47, "test"])]', $output);
    }

    public function testNamedParameters()
    {
        $output = _Attribute::create('Foobar')
            ->addParameter('seven', 7)
            ->toString();
        $this->assertEquals('#[Foobar(seven: 7)]', $output);

        $output = _Attribute::create('Foobar')
            ->addParameter('seven', 7)
            ->addParameter('true', true)
            ->addParameter('false', false)
            ->addParameter('null', null)
            ->addParameter('float', 47.11)
            ->addParameter('string', 'tobias')
            ->addParameter('array', [47, 'test'])
            ->toString();
        $this->assertEquals('#[Foobar(seven: 7, true: true, false: false, null: NULL, float: 47.11, string: "tobias", array: [47, "test"])]', $output);
    }

    public function testNested()
    {
        $nested = _Attribute::create('Baz')
            ->addParameter('name', 'tobias');

        $output = _Attribute::create('Foobar')
            ->addParameter('seven', 7)
            ->addParameter('nested', $nested)
            ->toString();
        $this->assertEquals('#[Foobar(seven: 7, nested: new Baz(name: "tobias"))]', $output);
    }
}
