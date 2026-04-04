<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Tests\Fixtures\DataCollector\CloneVarDataCollector;
use Symfony\Component\HttpKernel\Tests\Fixtures\UsePropertyInDestruct;
use Symfony\Component\HttpKernel\Tests\Fixtures\WithPublicObjectProperty;
use Symfony\Component\VarDumper\Cloner\VarCloner;

class DataCollectorTest extends TestCase
{
    public function testCloneVarStringWithScheme()
    {
        $c = new CloneVarDataCollector('scheme://foo');
        $c->collect(new Request(), new Response());
        $cloner = new VarCloner();

        $this->assertEquals($cloner->cloneVar('scheme://foo'), $c->getData());
    }

    public function testCloneVarExistingFilePath()
    {
        $c = new CloneVarDataCollector([$filePath = tempnam(sys_get_temp_dir(), 'clone_var_data_collector_')]);
        $c->collect(new Request(), new Response());

        $this->assertSame($filePath, $c->getData()[0]);
    }

    public function testClassPublicObjectProperty()
    {
        $parent = new WithPublicObjectProperty();
        $child = new WithPublicObjectProperty();

        $child->parent = $parent;

        $c = new CloneVarDataCollector($child);
        $c->collect(new Request(), new Response());

        $this->assertNotNull($c->getData()->parent);
    }

    public function testClassPublicObjectPropertyAsReference()
    {
        $parent = new WithPublicObjectProperty();
        $child = new WithPublicObjectProperty();

        $child->parent = &$parent;

        $c = new CloneVarDataCollector($child);
        $c->collect(new Request(), new Response());

        $this->assertNotNull($c->getData()->parent);
    }

    public function testClassUsePropertyInDestruct()
    {
        $parent = new UsePropertyInDestruct();
        $child = new UsePropertyInDestruct();

        $child->parent = $parent;

        $c = new CloneVarDataCollector($child);
        $c->collect(new Request(), new Response());

        $this->assertNotNull($c->getData()->parent);
    }

    public function testClassUsePropertyAsReferenceInDestruct()
    {
        $parent = new UsePropertyInDestruct();
        $child = new UsePropertyInDestruct();

        $child->parent = &$parent;

        $c = new CloneVarDataCollector($child);
        $c->collect(new Request(), new Response());

        $this->assertNotNull($c->getData()->parent);
    }

    public function testResolveDataUnwrapsDataObjects()
    {
        $cloner = new VarCloner();
        $data = [
            'scalar' => 'hello',
            'number' => 42,
            'cloned' => $cloner->cloneVar('resolved-value'),
            'nested' => [
                'inner_cloned' => $cloner->cloneVar(['a', 'b']),
                'inner_scalar' => true,
            ],
        ];

        $c = new CloneVarDataCollector(null);
        $method = new \ReflectionMethod($c, 'resolveData');

        $result = $method->invoke($c, $data);

        $this->assertSame('hello', $result['scalar']);
        $this->assertSame(42, $result['number']);
        $this->assertSame('resolved-value', $result['cloned']);
        $this->assertSame(['a', 'b'], $result['nested']['inner_cloned']);
        $this->assertTrue($result['nested']['inner_scalar']);
    }
}
