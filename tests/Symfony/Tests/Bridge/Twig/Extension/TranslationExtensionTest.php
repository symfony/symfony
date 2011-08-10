<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Bridge\Twig\Extension;

use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Tests\Bridge\Twig\TestCase;

class TranslationExtensionTest extends TestCase
{
    protected function setUp()
    {
        if (!class_exists('Twig_Environment')) {
            $this->markTestSkipped('Twig is not available.');
        }
    }

    public function testEscaping()
    {
        $output = $this->getTemplate('{% trans %}Percent: %value%%% (%msg%){% endtrans %}')->render(array('value' => 12, 'msg' => 'approx.'));

        $this->assertEquals('Percent: 12% (approx.)', $output);
    }

    /**
     * @dataProvider getTransTests
     */
    public function testTrans($template, $expected, array $variables = array())
    {
        if ($expected != $this->getTemplate($template)->render($variables)) {
            print $template."\n";
            $loader = new \Twig_Loader_Array(array('index' => $template));
            $twig = new \Twig_Environment($loader, array('debug' => true, 'cache' => false));
            $twig->addExtension(new TranslationExtension(new Translator('en', new MessageSelector())));

            echo $twig->compile($twig->parse($twig->tokenize($twig->getLoader()->getSource('index'), 'index')))."\n\n";
            $this->assertEquals($expected, $this->getTemplate($template)->render($variables));
        }

        $this->assertEquals($expected, $this->getTemplate($template)->render($variables));
    }

    public function getTransTests()
    {
        return array(
            // trans tag
            array('{% trans %}Hello{% endtrans %}', 'Hello'),
            array('{% trans %}%name%{% endtrans %}', 'Symfony2', array('name' => 'Symfony2')),

            array('{% trans from elsewhere %}Hello{% endtrans %}', 'Hello'),

            array('{% trans %}Hello %name%{% endtrans %}', 'Hello Symfony2', array('name' => 'Symfony2')),
            array('{% trans with { \'%name%\': \'Symfony2\' } %}Hello %name%{% endtrans %}', 'Hello Symfony2'),
            array('{% set vars = { \'%name%\': \'Symfony2\' } %}{% trans with vars %}Hello %name%{% endtrans %}', 'Hello Symfony2'),

            array('{% trans into "fr"%}Hello{% endtrans %}', 'Hello'),

            // transchoice
            array('{% transchoice count from "messages" %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtranschoice %}',
                'There is no apples', array('count' => 0)),
            array('{% transchoice count %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtranschoice %}',
                'There is 5 apples', array('count' => 5)),
            array('{% transchoice count %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples (%name%){% endtranschoice %}',
                'There is 5 apples (Symfony2)', array('count' => 5, 'name' => 'Symfony2')),
            array('{% transchoice count with { \'%name%\': \'Symfony2\' } %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples (%name%){% endtranschoice %}',
                'There is 5 apples (Symfony2)', array('count' => 5)),
            array('{% transchoice count into "fr"%}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtranschoice %}',
                'There is no apples', array('count' => 0)),

            // trans filter
            array('{{ "Hello"|trans }}', 'Hello'),
            array('{{ name|trans }}', 'Symfony2', array('name' => 'Symfony2')),
            array('{{ hello|trans({ \'%name%\': \'Symfony2\' }) }}', 'Hello Symfony2', array('hello' => 'Hello %name%')),
            array('{% set vars = { \'%name%\': \'Symfony2\' } %}{{ hello|trans(vars) }}', 'Hello Symfony2', array('hello' => 'Hello %name%')),
            array('{{ "Hello"|trans({}, "messages", "fr") }}', 'Hello'),

            // transchoice filter
            array('{{ "{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples"|transchoice(count) }}', 'There is 5 apples', array('count' => 5)),
            array('{{ text|transchoice(5, {\'%name%\': \'Symfony2\'}) }}', 'There is 5 apples (Symfony2)', array('text' => '{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples (%name%)')),
            array('{{ "{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples"|transchoice(count, {}, "messages", "fr") }}', 'There is 5 apples', array('count' => 5)),
        );
    }

    protected function getTemplate($template)
    {
        $loader = new \Twig_Loader_Array(array('index' => $template));
        $twig = new \Twig_Environment($loader, array('debug' => true, 'cache' => false));
        $twig->addExtension(new TranslationExtension(new Translator('en', new MessageSelector())));

        return $twig->loadTemplate('index');
    }
}
