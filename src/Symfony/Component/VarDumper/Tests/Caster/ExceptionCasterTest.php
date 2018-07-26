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

use Symfony\Component\VarDumper\Caster\FrameStub;
use Symfony\Component\VarDumper\Test\VarDumperTestCase;

class ExceptionCasterTest extends VarDumperTestCase
{
    /**
     * @requires function Twig\Template::getSourceContext
     */
    public function testFrameWithTwig()
    {
        require_once \dirname(__DIR__).'/Fixtures/Twig.php';

        $f = array(
            new FrameStub(array(
                'file' => \dirname(__DIR__).'/Fixtures/Twig.php',
                'line' => 21,
                'class' => '__TwigTemplate_VarDumperFixture_u75a09',
            )),
            new FrameStub(array(
                'file' => \dirname(__DIR__).'/Fixtures/Twig.php',
                'line' => 21,
                'class' => '__TwigTemplate_VarDumperFixture_u75a09',
                'object' => new \__TwigTemplate_VarDumperFixture_u75a09(null, false),
            )),
        );

        $expectedDump = <<<'EODUMP'
array:2 [
  0 => {
    class: "__TwigTemplate_VarDumperFixture_u75a09"
    src: {
      %sTwig.php:21: """
            // line 2\n
            throw new \Exception('Foobar');\n
        }\n
        """
      bar.twig:2: """
        foo bar\n
          twig source\n
        \n
        """
    }
  }
  1 => {
    class: "__TwigTemplate_VarDumperFixture_u75a09"
    object: __TwigTemplate_VarDumperFixture_u75a09 {
    %A
    }
    src: {
      %sTwig.php:21: """
            // line 2\n
            throw new \Exception('Foobar');\n
        }\n
        """
      foo.twig:2: """
        foo bar\n
          twig source\n
        \n
        """
    }
  }
]

EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $f);
    }
}
