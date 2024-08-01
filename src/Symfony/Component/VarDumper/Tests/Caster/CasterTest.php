<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class CasterTest extends TestCase
{
    use VarDumperTestTrait;

    private static array $referenceArray = [
        'null' => null,
        'empty' => false,
        'public' => 'pub',
        "\0~\0virtual" => 'virt',
        "\0+\0dynamic" => 'dyn',
        "\0*\0protected" => 'prot',
        "\0Foo\0private" => 'priv',
    ];

    /**
     * @dataProvider provideFilter
     */
    public function testFilter($filter, $expectedDiff, $listedProperties = null)
    {
        if (null === $listedProperties) {
            $filteredArray = Caster::filter(self::$referenceArray, $filter);
        } else {
            $filteredArray = Caster::filter(self::$referenceArray, $filter, $listedProperties);
        }

        $this->assertSame($expectedDiff, array_diff_assoc(self::$referenceArray, $filteredArray));
    }

    public static function provideFilter()
    {
        return [
            [
                0,
                [],
            ],
            [
                Caster::EXCLUDE_PUBLIC,
                [
                    'null' => null,
                    'empty' => false,
                    'public' => 'pub',
                ],
            ],
            [
                Caster::EXCLUDE_NULL,
                [
                    'null' => null,
                ],
            ],
            [
                Caster::EXCLUDE_EMPTY,
                [
                    'null' => null,
                    'empty' => false,
                ],
            ],
            [
                Caster::EXCLUDE_VIRTUAL,
                [
                    "\0~\0virtual" => 'virt',
                ],
            ],
            [
                Caster::EXCLUDE_DYNAMIC,
                [
                    "\0+\0dynamic" => 'dyn',
                ],
            ],
            [
                Caster::EXCLUDE_PROTECTED,
                [
                    "\0*\0protected" => 'prot',
                ],
            ],
            [
                Caster::EXCLUDE_PRIVATE,
                [
                    "\0Foo\0private" => 'priv',
                ],
            ],
            [
                Caster::EXCLUDE_VERBOSE,
                [
                    'public' => 'pub',
                    "\0*\0protected" => 'prot',
                ],
                ['public', "\0*\0protected"],
            ],
            [
                Caster::EXCLUDE_NOT_IMPORTANT,
                [
                    'null' => null,
                    'empty' => false,
                    "\0~\0virtual" => 'virt',
                    "\0+\0dynamic" => 'dyn',
                    "\0Foo\0private" => 'priv',
                ],
                ['public', "\0*\0protected"],
            ],
            [
                Caster::EXCLUDE_VIRTUAL | Caster::EXCLUDE_DYNAMIC,
                [
                    "\0~\0virtual" => 'virt',
                    "\0+\0dynamic" => 'dyn',
                ],
            ],
            [
                Caster::EXCLUDE_NOT_IMPORTANT | Caster::EXCLUDE_VERBOSE,
                self::$referenceArray,
                ['public', "\0*\0protected"],
            ],
            [
                Caster::EXCLUDE_NOT_IMPORTANT | Caster::EXCLUDE_EMPTY,
                [
                    'null' => null,
                    'empty' => false,
                    "\0~\0virtual" => 'virt',
                    "\0+\0dynamic" => 'dyn',
                    "\0*\0protected" => 'prot',
                    "\0Foo\0private" => 'priv',
                ],
                ['public', 'empty'],
            ],
            [
                Caster::EXCLUDE_VERBOSE | Caster::EXCLUDE_EMPTY | Caster::EXCLUDE_STRICT,
                [
                    'empty' => false,
                ],
                ['public', 'empty'],
            ],
        ];
    }

    public function testAnonymousClass()
    {
        $c = eval('return new class extends stdClass { private $foo = "foo"; };');

        $this->assertDumpMatchesFormat(
            <<<'EOTXT'
stdClass@anonymous {
  -foo: "foo"
}
EOTXT
            , $c
        );

        $c = eval('return new class implements \Countable { private $foo = "foo"; public function count(): int { return 0; } };');

        $this->assertDumpMatchesFormat(
            <<<'EOTXT'
Countable@anonymous {
  -foo: "foo"
}
EOTXT
            , $c
        );
    }

    public function testTypeErrorInDebugInfo()
    {
        $this->assertDumpMatchesFormat('class@anonymous {}', new class {
            public function __debugInfo(): array
            {
                return ['class' => \get_class(null)];
            }
        });
    }

    public function testClassHierarchy()
    {
        $this->assertDumpMatchesFormat(<<<'DUMP'
            Symfony\Component\VarDumper\Tests\Caster\B {
              +a: "a"
              #b: "b"
              -c: "c"
              +d: "d"
              #e: "e"
              -f: "f"
            }
            DUMP, new B());
    }
}

class A
{
    public $a = 'a';
    protected $b = 'b';
    private $c = 'c';
}

class B extends A
{
    public $d = 'd';
    protected $e = 'e';
    private $f = 'f';
}
