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

use Symfony\Bridge\Twig\Extension\RoutingExtension;

class RoutingExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getEscapingTemplates
     */
    public function testEscaping($template, $mustBeEscaped)
    {
        $twig = new \Twig_Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock(), array('debug' => true, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0));
        $twig->addExtension(new RoutingExtension($this->getMockBuilder('Symfony\Component\Routing\Generator\UrlGeneratorInterface')->getMock()));

        $nodes = $twig->parse($twig->tokenize(new \Twig_Source($template, '')));

        $this->assertSame($mustBeEscaped, $nodes->getNode('body')->getNode(0)->getNode('expr') instanceof \Twig_Node_Expression_Filter);
    }

    public function getEscapingTemplates()
    {
        return array(
            array('{{ path("foo") }}', false),
            array('{{ path("foo", {}) }}', false),
            array('{{ path("foo", { foo: "foo" }) }}', false),
            array('{{ path("foo", foo) }}', true),
            array('{{ path("foo", { foo: foo }) }}', true),
            array('{{ path("foo", { foo: ["foo", "bar"] }) }}', true),
            array('{{ path("foo", { foo: "foo", bar: "bar" }) }}', true),

            array('{{ path(name = "foo", parameters = {}) }}', false),
            array('{{ path(name = "foo", parameters = { foo: "foo" }) }}', false),
            array('{{ path(name = "foo", parameters = foo) }}', true),
            array('{{ path(name = "foo", parameters = { foo: ["foo", "bar"] }) }}', true),
            array('{{ path(name = "foo", parameters = { foo: foo }) }}', true),
            array('{{ path(name = "foo", parameters = { foo: "foo", bar: "bar" }) }}', true),
        );
    }
}
