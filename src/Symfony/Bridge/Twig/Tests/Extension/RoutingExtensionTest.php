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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Twig\Environment;
use Twig\Node\Expression\FilterExpression;
use Twig\Source;

class RoutingExtensionTest extends TestCase
{
    /**
     * @dataProvider getEscapingTemplates
     */
    public function testEscaping($template, $mustBeEscaped)
    {
        $twig = new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock(), ['debug' => true, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0]);
        $twig->addExtension(new RoutingExtension($this->getMockBuilder('Symfony\Component\Routing\Generator\UrlGeneratorInterface')->getMock()));

        $nodes = $twig->parse($twig->tokenize(new Source($template, '')));

        $this->assertSame($mustBeEscaped, $nodes->getNode('body')->getNode(0)->getNode('expr') instanceof FilterExpression);
    }

    public function getEscapingTemplates()
    {
        return [
            ['{{ path("foo") }}', false],
            ['{{ path("foo", {}) }}', false],
            ['{{ path("foo", { foo: "foo" }) }}', false],
            ['{{ path("foo", foo) }}', true],
            ['{{ path("foo", { foo: foo }) }}', true],
            ['{{ path("foo", { foo: ["foo", "bar"] }) }}', true],
            ['{{ path("foo", { foo: "foo", bar: "bar" }) }}', true],

            ['{{ path(name = "foo", parameters = {}) }}', false],
            ['{{ path(name = "foo", parameters = { foo: "foo" }) }}', false],
            ['{{ path(name = "foo", parameters = foo) }}', true],
            ['{{ path(name = "foo", parameters = { foo: ["foo", "bar"] }) }}', true],
            ['{{ path(name = "foo", parameters = { foo: foo }) }}', true],
            ['{{ path(name = "foo", parameters = { foo: "foo", bar: "bar" }) }}', true],
        ];
    }
}
