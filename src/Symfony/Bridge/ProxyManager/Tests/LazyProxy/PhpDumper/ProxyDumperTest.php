<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\ProxyManager\Tests\LazyProxy\PhpDumper;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\ProxyManager\LazyProxy\PhpDumper\ProxyDumper;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Tests for {@see \Symfony\Bridge\ProxyManager\LazyProxy\PhpDumper\ProxyDumper}.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
class ProxyDumperTest extends TestCase
{
    /**
     * @var ProxyDumper
     */
    protected $dumper;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->dumper = new ProxyDumper();
    }

    /**
     * @dataProvider getProxyCandidates
     */
    public function testIsProxyCandidate(Definition $definition, bool $expected)
    {
        $this->assertSame($expected, $this->dumper->isProxyCandidate($definition));
    }

    public function testGetProxyCode()
    {
        $definition = new Definition(__CLASS__);

        $definition->setLazy(true);

        $code = $this->dumper->getProxyCode($definition);

        $this->assertStringMatchesFormat(
            '%Aclass ProxyDumperTest%aextends%w'
                .'\Symfony\Bridge\ProxyManager\Tests\LazyProxy\PhpDumper\ProxyDumperTest%a',
            $code
        );
    }

    public function testDeterministicProxyCode()
    {
        $definition = new Definition(__CLASS__);
        $definition->setLazy(true);

        $this->assertSame($this->dumper->getProxyCode($definition), $this->dumper->getProxyCode($definition));
    }

    public function testGetProxyFactoryCode()
    {
        $definition = new Definition(__CLASS__);

        $definition->setLazy(true);

        $code = $this->dumper->getProxyFactoryCode($definition, 'foo', '$this->getFoo2Service(false)');

        $this->assertStringMatchesFormat(
            '%A$wrappedInstance = $this->getFoo2Service(false);%w$proxy->setProxyInitializer(null);%A',
            $code
        );
    }

    /**
     * @dataProvider getPrivatePublicDefinitions
     */
    public function testCorrectAssigning(Definition $definition, $access)
    {
        $definition->setLazy(true);

        $code = $this->dumper->getProxyFactoryCode($definition, 'foo', '$this->getFoo2Service(false)');

        $this->assertStringMatchesFormat('%A$this->'.$access.'[\'foo\'] = %A', $code);
    }

    public function getPrivatePublicDefinitions()
    {
        return array(
            array(
                (new Definition(__CLASS__))
                    ->setPublic(false),
                'privates',
            ),
            array(
                (new Definition(__CLASS__))
                    ->setPublic(true),
                'services',
            ),
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Missing factory code to construct the service "foo".
     */
    public function testGetProxyFactoryCodeWithoutCustomMethod()
    {
        $definition = new Definition(__CLASS__);
        $definition->setLazy(true);
        $this->dumper->getProxyFactoryCode($definition, 'foo');
    }

    public function testGetProxyFactoryCodeForInterface()
    {
        $class = DummyClass::class;
        $definition = new Definition($class);

        $definition->setLazy(true);
        $definition->addTag('proxy', array('interface' => DummyInterface::class));
        $definition->addTag('proxy', array('interface' => SunnyInterface::class));

        $implem = "<?php\n\n".$this->dumper->getProxyCode($definition);
        $factory = $this->dumper->getProxyFactoryCode($definition, 'foo', '$this->getFooService(false)');
        $factory = <<<EOPHP
<?php

return new class
{
    public \$proxyClass;
    private \$privates = array();

    public function getFooService(\$lazyLoad = true)
    {
{$factory}        return new {$class}();
    }

    protected function createProxy(\$class, \Closure \$factory)
    {
        \$this->proxyClass = \$class;

        return \$factory();
    }
};

EOPHP;

        $implem = preg_replace('#\n    /\*\*.*?\*/#s', '', $implem);
        $implem = str_replace('getWrappedValueHolderValue() : ?object', 'getWrappedValueHolderValue()', $implem);
        $implem = str_replace("array(\n        \n    );", "[\n        \n    ];", $implem);
        $this->assertStringEqualsFile(__DIR__.'/Fixtures/proxy-implem.php', $implem);
        $this->assertStringEqualsFile(__DIR__.'/Fixtures/proxy-factory.php', $factory);

        require_once __DIR__.'/Fixtures/proxy-implem.php';
        $factory = require __DIR__.'/Fixtures/proxy-factory.php';

        $foo = $factory->getFooService();

        $this->assertInstanceof($factory->proxyClass, $foo);
        $this->assertInstanceof(DummyInterface::class, $foo);
        $this->assertInstanceof(SunnyInterface::class, $foo);
        $this->assertNotInstanceof(DummyClass::class, $foo);
        $this->assertSame($foo, $foo->dummy());

        $foo->dynamicProp = 123;
        $this->assertSame(123, @$foo->dynamicProp);
    }

    /**
     * @return array
     */
    public function getProxyCandidates()
    {
        $definitions = array(
            array(new Definition(__CLASS__), true),
            array(new Definition('stdClass'), true),
            array(new Definition(uniqid('foo', true)), false),
            array(new Definition(), false),
        );

        array_map(
            function ($definition) {
                $definition[0]->setLazy(true);
            },
            $definitions
        );

        return $definitions;
    }
}

final class DummyClass implements DummyInterface, SunnyInterface
{
    public function dummy()
    {
        return $this;
    }

    public function sunny()
    {
    }

    public function &dummyRef()
    {
        return $this->ref;
    }
}

interface DummyInterface
{
    public function dummy();

    public function &dummyRef();
}

interface SunnyInterface
{
    public function dummy();

    public function sunny();
}
