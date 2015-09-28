<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests;

use Symfony\Bridge\Twig\Debug\TwigFlattenExceptionProcessor;
use Symfony\Component\Debug\ExceptionFlattener;

class TwigFlattenExceptionProcessorTest extends \PHPUnit_Framework_TestCase
{
    public function testProcess()
    {
        $contents = array(
            'base' => "{% block content '' %}",
            'layout' => "{% extends 'base' %}\n{% block content %}\nfoo\n{{ foo.foo }}\n{% endblock %}",
            'index' => "{% extends 'layout' %}\n{% block content %}\n{{ parent() }}\n{% endblock %}",
        );

        $files = array(
            'base' => (object) array('name' => 'base', 'content' => $contents['base'], 'type' => 'twig'),
            'layout' => (object) array('name' => 'layout', 'content' => $contents['layout'], 'type' => 'twig'),
            'index' => (object) array('name' => 'index', 'content' => $contents['index'], 'type' => 'twig'),
        );

        $twig = new \Twig_Environment(new \Twig_Loader_Array($contents), array('strict_variables' => true));

        try {
            $twig->render('index', array('foo' => 'foo'));
        } catch (\Twig_Error $exception) {
        }

        $flattener = new ExceptionFlattener(array(new TwigFlattenExceptionProcessor($twig)));
        $trace = $flattener->flatten($exception)->getTrace();

        $this->assertEquals(array(array('line' => 4, 'file' => $files['layout'])), $trace[-1]['related_codes']);
        $this->assertEquals(array(array('line' => 4, 'file' => $files['layout'])), $trace[0]['related_codes']);
        $this->assertEquals(array(array('line' => 3, 'file' => $files['index'])), $trace[3]['related_codes']);
        $this->assertEquals(array(array('line' => 1, 'file' => $files['base'])), $trace[5]['related_codes']);
        $this->assertEquals(array(array('line' => 1, 'file' => $files['layout'])), $trace[8]['related_codes']);
        $this->assertEquals(array(array('line' => 1, 'file' => $files['index'])), $trace[11]['related_codes']);
    }

    public function testProcessRealTwigFile()
    {
        $file = (object) array(
            'name' => 'error.html.twig',
            'path' => dirname(__DIR__).'/Fixtures/templates/error.html.twig',
            'type' => 'twig',
        );

        $twig = new \Twig_Environment(new \Twig_Loader_Filesystem(array(dirname($file->path))), array('strict_variables' => true));

        try {
            $twig->render($file->name, array('foo' => 'foo'));
        } catch (\Twig_Error $exception) {
        }

        $flattener = new ExceptionFlattener(array(new TwigFlattenExceptionProcessor($twig)));
        $trace = $flattener->flatten($exception)->getTrace();

        $this->assertEquals(array(array('line' => 2, 'file' => $file)), $trace[-1]['related_codes']);
    }
}
