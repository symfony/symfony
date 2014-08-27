<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Symfony\Bridge\Twig\Extension\DumpExtension;
use Symfony\Component\VarDumper\VarDumper;

class DumpExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getDumpParams
     */
    public function testDebugDump($template, $debug, $expectedOutput, $expectedDumped)
    {
        $twig = new \Twig_Environment(new \Twig_Loader_String(), array(
            'debug' => $debug,
            'cache' => false,
            'optimizations' => 0,
        ));
        $twig->addExtension(new DumpExtension());

        $dumped = null;
        $exception = null;
        $prevDumper = VarDumper::setHandler(function ($var) use (&$dumped) {$dumped = $var;});

        try {
            $this->assertEquals($expectedOutput, $twig->render($template));
        } catch (\Exception $exception) {
        }

        VarDumper::setHandler($prevDumper);

        if (null !== $exception) {
            throw $exception;
        }

        $this->assertSame($expectedDumped, $dumped);
    }

    public function getDumpParams()
    {
        return array(
            array('A{% dump %}B', true, 'AB', array()),
            array('A{% set foo="bar"%}B{% dump %}C', true, 'ABC', array('foo' => 'bar')),
            array('A{% dump %}B', false, 'AB', null),
        );
    }
}
