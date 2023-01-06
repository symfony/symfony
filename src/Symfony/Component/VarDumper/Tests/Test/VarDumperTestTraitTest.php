<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Test;

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

class VarDumperTestTraitTest extends TestCase
{
    use VarDumperTestTrait;

    public function testItComparesLargeData()
    {
        $howMany = 700;
        $data = array_fill_keys(range(0, $howMany), ['a', 'b', 'c', 'd']);

        $expected = sprintf("array:%d [\n", $howMany + 1);
        for ($i = 0; $i <= $howMany; ++$i) {
            $expected .= <<<EODUMP
  $i => array:4 [
    0 => "a"
    1 => "b"
    2 => "c"
    3 => "d"
  ]\n
EODUMP;
        }
        $expected .= "]\n";

        $this->assertDumpEquals($expected, $data);
    }

    public function testAllowsNonScalarExpectation()
    {
        $this->assertDumpEquals(new \ArrayObject(['bim' => 'bam']), new \ArrayObject(['bim' => 'bam']));
    }

    public function testItCanBeConfigured()
    {
        $this->setUpVarDumper($casters = [
            \DateTimeInterface::class => static function (\DateTimeInterface $date, array $a, Stub $stub): array {
                $stub->class = 'DateTimeImmutable';

                return ['date' => $date->format('d/m/Y')];
            },
        ], CliDumper::DUMP_LIGHT_ARRAY | CliDumper::DUMP_COMMA_SEPARATOR);

        $this->assertSame(CliDumper::DUMP_LIGHT_ARRAY | CliDumper::DUMP_COMMA_SEPARATOR, $this->varDumperConfig['flags']);
        $this->assertSame($casters, $this->varDumperConfig['casters']);

        $this->assertDumpEquals(<<<DUMP
[
  1,
  2,
  DateTimeImmutable {
    +date: "09/07/2019"
  }
]
DUMP
            , [1, 2, new \DateTimeImmutable('2019-07-09T0:00:00+00:00')]);

        $this->tearDownVarDumper();

        $this->assertNull($this->varDumperConfig['flags']);
        $this->assertSame([], $this->varDumperConfig['casters']);
    }
}
