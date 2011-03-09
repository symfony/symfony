<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests;

use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Bundle\TwigBundle\Extension\TransExtension;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageSelector;

class TransTest extends TestCase
{
    /**
     * @dataProvider getTransTests
     */
    public function testTrans($template, $expected, array $variables = array())
    {
        if ($expected != $this->getTemplate($template)->render($variables)) {
            print $template."\n";
            $loader = new \Twig_Loader_Array(array('index' => $template));
            $twig = new \Twig_Environment($loader, array('debug' => true, 'cache' => false));
            $twig->addExtension(new TransExtension(new Translator('en', new MessageSelector())));

            echo $twig->compile($twig->parse($twig->tokenize($twig->getLoader()->getSource('index'), 'index')))."\n\n";
            $this->assertEquals($expected, $this->getTemplate($template)->render($variables));
        }

        $this->assertEquals($expected, $this->getTemplate($template)->render($variables));
    }

    public function getTransTests()
    {
        return array(
            // trans tag
            array('{% trans "Hello" %}', 'Hello'),
            array('{% trans "Hello %name%" %}', 'Hello Symfony2', array('name' => 'Symfony2')),
            array('{% trans name %}', 'Symfony2', array('name' => 'Symfony2')),
            array('{% trans hello with { \'%name%\': \'Symfony2\' } %}', 'Hello Symfony2', array('hello' => 'Hello %name%')),
            array('{% set vars = { \'%name%\': \'Symfony2\' } %}{% trans hello with vars %}', 'Hello Symfony2', array('hello' => 'Hello %name%')),

            array('{% trans %}Hello{% endtrans %}', 'Hello'),
            array('{% trans %}%name%{% endtrans %}', 'Symfony2', array('name' => 'Symfony2')),

            array('{% trans "Hello" from elsewhere %}', 'Hello'),
            array('{% trans from elsewhere %}Hello{% endtrans %}', 'Hello'),

            array('{% trans %}Hello %name%{% endtrans %}', 'Hello Symfony2', array('name' => 'Symfony2')),
            array('{% trans with { \'%name%\': \'Symfony2\' } %}Hello %name%{% endtrans %}', 'Hello Symfony2'),
            array('{% set vars = { \'%name%\': \'Symfony2\' } %}{% trans with vars %}Hello %name%{% endtrans %}', 'Hello Symfony2'),

            // transchoice
            array('{% transchoice count from "messages" %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtranschoice %}',
                'There is no apples', array('count' => 0)),
            array('{% transchoice count %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtranschoice %}',
                'There is 5 apples', array('count' => 5)),
            array('{% transchoice count %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples (%name%){% endtranschoice %}',
                'There is 5 apples (Symfony2)', array('count' => 5, 'name' => 'Symfony2')),
            array('{% transchoice count with { \'%name%\': \'Symfony2\' } %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples (%name%){% endtranschoice %}',
                'There is 5 apples (Symfony2)', array('count' => 5)),

            // trans filter
            array('{{ "Hello"|trans }}', 'Hello'),
            array('{{ name|trans }}', 'Symfony2', array('name' => 'Symfony2')),
            array('{{ hello|trans({ \'%name%\': \'Symfony2\' }) }}', 'Hello Symfony2', array('hello' => 'Hello %name%')),
            array('{% set vars = { \'%name%\': \'Symfony2\' } %}{{ hello|trans(vars) }}', 'Hello Symfony2', array('hello' => 'Hello %name%')),
        );
    }

    protected function getTemplate($template)
    {
        $loader = new \Twig_Loader_Array(array('index' => $template));
        $twig = new \Twig_Environment($loader, array('debug' => true, 'cache' => false));
        $twig->addExtension(new TransExtension(new Translator('en', new MessageSelector())));

        return $twig->loadTemplate('index');
    }
}
