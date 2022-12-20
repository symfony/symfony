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
use Symfony\Component\DependencyInjection\LazyProxy\PhpDumper\DumperInterface;

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
    protected function setUp(): void
    {
        $this->dumper = new ProxyDumper();
    }

    /**
     * @dataProvider getProxyCandidates
     */
    public function testIsProxyCandidate(Definition $definition, bool $expected)
    {
        self::assertSame($expected, $this->dumper->isProxyCandidate($definition));
    }

    public function testGetProxyCode()
    {
        $definition = new Definition(__CLASS__);

        $definition->setLazy(true);

        $code = $this->dumper->getProxyCode($definition);

        self::assertStringMatchesFormat('%Aclass ProxyDumperTest%aextends%w'
            .'\Symfony\Bridge\ProxyManager\Tests\LazyProxy\PhpDumper\ProxyDumperTest%a', $code);
    }

    public function testDeterministicProxyCode()
    {
        $definition = new Definition(__CLASS__);
        $definition->setLazy(true);

        self::assertSame($this->dumper->getProxyCode($definition), $this->dumper->getProxyCode($definition));
    }

    public function testGetProxyFactoryCode()
    {
        $definition = new Definition(__CLASS__);

        $definition->setLazy(true);

        $code = $this->dumper->getProxyFactoryCode($definition, 'foo', '$this->getFoo2Service(false)');

        self::assertStringMatchesFormat('%A$wrappedInstance = $this->getFoo2Service(false);%w$proxy->setProxyInitializer(null);%A', $code);
    }

    /**
     * @dataProvider getPrivatePublicDefinitions
     */
    public function testCorrectAssigning(Definition $definition, $access)
    {
        $definition->setLazy(true);

        $code = $this->dumper->getProxyFactoryCode($definition, 'foo', '$this->getFoo2Service(false)');

        self::assertStringMatchesFormat('%A$this->'.$access.'[\'foo\'] = %A', $code);
    }

    public function getPrivatePublicDefinitions()
    {
        return [
            [
                (new Definition(__CLASS__))
                    ->setPublic(false),
                'privates',
            ],
            [
                (new Definition(__CLASS__))
                    ->setPublic(true),
                'services',
            ],
        ];
    }

    public function testGetProxyFactoryCodeForInterface()
    {
        $class = DummyClass::class;
        $definition = new Definition($class);

        $definition->setLazy(true);
        $definition->addTag('proxy', ['interface' => DummyInterface::class]);
        $definition->addTag('proxy', ['interface' => SunnyInterface::class]);

        $implem = "<?php\n\n".$this->dumper->getProxyCode($definition);
        $factory = $this->dumper->getProxyFactoryCode($definition, 'foo', '$this->getFooService(false)');
        $factory = <<<EOPHP
<?php

return new class
{
    public \$proxyClass;
    private \$privates = [];

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
        $implem = str_replace("array(\n        \n    );", "[\n        \n    ];", $implem);

        self::assertStringMatchesFormatFile(__DIR__.'/Fixtures/proxy-implem.php', $implem);
        self::assertStringEqualsFile(__DIR__.'/Fixtures/proxy-factory.php', $factory);

        eval(preg_replace('/^<\?php/', '', $implem));
        $factory = require __DIR__.'/Fixtures/proxy-factory.php';

        $foo = $factory->getFooService();

        self::assertInstanceof($factory->proxyClass, $foo);
        self::assertInstanceof(DummyInterface::class, $foo);
        self::assertInstanceof(SunnyInterface::class, $foo);
        self::assertNotInstanceof(DummyClass::class, $foo);
        self::assertSame($foo, $foo->dummy());

        $foo->dynamicProp = 123;
        self::assertSame(123, @$foo->dynamicProp);
    }

    public function getProxyCandidates(): array
    {
        $definitions = [
            [new Definition(__CLASS__), true],
            [new Definition('stdClass'), true],
            [new Definition(DumperInterface::class), true],
            [new Definition(uniqid('foo', true)), false],
            [new Definition(), false],
        ];

        array_map(
            function ($definition) {
                $definition[0]->setLazy(true);
            },
            $definitions
        );

        return $definitions;
    }
}

#[\AllowDynamicProperties]
final class DummyClass implements DummyInterface, SunnyInterface
{
    private $ref;

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
