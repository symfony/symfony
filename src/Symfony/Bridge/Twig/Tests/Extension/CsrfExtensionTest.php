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
use Symfony\Bridge\Twig\Extension\CsrfExtension;
use Symfony\Bridge\Twig\Extension\CsrfRuntime;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

class CsrfExtensionTest extends TestCase
{
    public function testCsrfInputField()
    {
        $this->assertSame(
            '<input type="hidden" name="_token" value="T0K3N" data-controller="csrf-protection">',
            $this->render('{{ csrf_input_field("delete-item") }}'),
        );
    }

    public function testCsrfInputFieldWithCustomName()
    {
        $this->assertSame(
            '<input type="hidden" name="_csrf_token" value="T0K3N" data-controller="csrf-protection">',
            $this->render('{{ csrf_input_field("delete-item", field_name: "_csrf_token") }}'),
        );
    }

    public function testCsrfInputFieldEscapesNameAndToken()
    {
        $this->assertSame(
            '<input type="hidden" name="&quot;&gt;&lt;script&gt;&amp;&#039;" value="&quot;&gt;&lt;script&gt;&amp;&#039;" data-controller="csrf-protection">',
            $this->render('{{ csrf_input_field("delete-item", field_name) }}', '"><script>&\'', ['field_name' => '"><script>&\'']),
        );
    }

    public function testCsrfInputFieldUsesTemplateCharset()
    {
        $this->assertSame(
            "<input type=\"hidden\" name=\"caf\xe9\" value=\"caf\xe9\" data-controller=\"csrf-protection\">",
            $this->render('{{ csrf_input_field("delete-item", field_name) }}', "caf\xe9", ['field_name' => "caf\xe9"], 'ISO-8859-1'),
        );
    }

    private function render(string $template, string $token = 'T0K3N', array $context = [], string $charset = 'UTF-8'): string
    {
        $tokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $tokenManager->expects($this->once())->method('getToken')->with('delete-item')->willReturn(new CsrfToken('delete-item', $token));

        $twig = new Environment(new ArrayLoader(['template' => $template]), ['strict_variables' => true, 'charset' => $charset]);
        $twig->addExtension(new CsrfExtension());
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            CsrfRuntime::class => static fn () => new CsrfRuntime($tokenManager),
        ]));

        return $twig->render('template', $context);
    }
}
