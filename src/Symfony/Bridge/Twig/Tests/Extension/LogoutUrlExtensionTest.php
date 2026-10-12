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
use Symfony\Bridge\Twig\Extension\LogoutUrlExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class LogoutUrlExtensionTest extends TestCase
{
    public function testLogoutButtonRendersTheLogoutForm()
    {
        $this->assertSame(
            '<form method="post" action="/logout"><input type="hidden" name="_csrf_token" value="T0K3N"><button type="submit">Log out</button></form>',
            $this->render('{{ logout_button() }}'),
        );
    }

    public function testLogoutButtonRendersTheFormOfTheGivenFirewall()
    {
        $this->assertSame(
            '<form method="post" action="/admin/logout"><button type="submit">Sign out</button></form>',
            $this->render('{{ logout_button("Sign out", "admin") }}'),
        );
    }

    public function testLogoutButtonAddsTheCsrfFieldAttributesToTheCsrfFieldOnly()
    {
        $this->assertSame(
            '<form method="post" action="/logout"><input type="hidden" name="_csrf_token" value="T0K3N" data-controller="csrf-protection"><button type="submit">Log out</button></form>',
            $this->render('{{ logout_button() }}', ['data-controller' => 'csrf-protection']),
        );
        $this->assertSame(
            '<form method="post" action="/admin/logout"><button type="submit">Log out</button></form>',
            $this->render('{{ logout_button(key: "admin") }}', ['data-controller' => 'csrf-protection']),
        );
    }

    public function testLogoutButtonRendersTheAttributesOfTheButtonAndOfTheForm()
    {
        $this->assertSame(
            '<form method="post" action="/admin/logout" class="inline"><button type="submit" class="link" data-turbo="false">Sign out</button></form>',
            $this->render('{{ logout_button("Sign out", "admin", {class: "link", "data-turbo": "false", hidden: false}, {class: "inline", action: "/elsewhere"}) }}'),
        );
        $this->assertSame(
            '<form method="post" action="/admin/logout" class="inline"><button type="submit" class="link" disabled="disabled">Log out</button></form>',
            $this->render('{{ logout_button(attr: {class: "link", disabled: true}, form_attr: {class: "inline"}, key: "admin") }}'),
        );
    }

    public function testLogoutButtonEscapesWhatItRenders()
    {
        $this->assertSame(
            '<form method="post" action="/log&quot;out&amp;"><button type="submit" title="&quot;&gt;&lt;script&gt;">&lt;b&gt;Bye&lt;/b&gt;</button></form>',
            $this->render('{{ logout_button("<b>Bye</b>", "escaped", {title: \'"><script>\'}) }}'),
        );
        $this->assertSame(
            '<form method="post" action="/logout"><input type="hidden" name="_csrf_token" value="&lt;&quot;&gt;"><button type="submit">Log out</button></form>',
            $this->render('{{ logout_button() }}', [], '<">'),
        );
    }

    public function testLogoutButtonRendersAMarkupLabelAsIs()
    {
        $this->assertSame(
            '<form method="post" action="/admin/logout"><button type="submit"><i class="icon"></i> Log out</button></form>',
            $this->render('{% set label %}<i class="icon"></i> Log out{% endset %}{{ logout_button(label, "admin") }}'),
        );
    }

    private function render(string $template, array $csrfFieldAttr = [], string $token = 'T0K3N'): string
    {
        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('getToken')->willReturn(new CsrfToken('logout', $token));

        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $generator = new LogoutUrlGenerator($requestStack);
        $generator->registerListener('main', '/logout', 'logout', '_csrf_token', $csrfTokenManager);
        $generator->registerListener('admin', '/admin/logout', null, null);
        $generator->registerListener('escaped', '/log"out&', null, null);
        $generator->setCurrentFirewall('main');

        $twig = new Environment(new ArrayLoader(['template' => $template]), ['strict_variables' => true]);
        $twig->addExtension(new LogoutUrlExtension($generator, $csrfFieldAttr));

        return $twig->render('template');
    }
}
