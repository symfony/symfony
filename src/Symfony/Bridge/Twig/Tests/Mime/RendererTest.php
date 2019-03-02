<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Mime;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\Renderer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\Multipart\RelatedPart;
use Symfony\Component\Mime\Part\TextPart;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class RendererTest extends TestCase
{
    public function testRenderTextOnly(): void
    {
        $email = $this->prepareEmail(null, 'Text', null);
        $this->assertEquals('Text', $email->getBody()->bodyToString());
    }

    public function testRenderHtmlOnly(): void
    {
        $email = $this->prepareEmail(null, null, '<b>HTML</b>');
        $body = $email->getBody();
        $this->assertInstanceOf(AlternativePart::class, $body);
        $this->assertEquals('HTML', $body->getParts()[0]->bodyToString());
        $this->assertEquals('<b>HTML</b>', $body->getParts()[1]->bodyToString());
    }

    public function testRenderHtmlOnlyWithTextSet(): void
    {
        $email = $this->prepareEmail(null, null, '<b>HTML</b>');
        $email->text('Text');
        $body = $email->getBody();
        $this->assertInstanceOf(AlternativePart::class, $body);
        $this->assertEquals('Text', $body->getParts()[0]->bodyToString());
        $this->assertEquals('<b>HTML</b>', $body->getParts()[1]->bodyToString());
    }

    public function testRenderTextAndHtml(): void
    {
        $email = $this->prepareEmail(null, 'Text', '<b>HTML</b>');
        $body = $email->getBody();
        $this->assertInstanceOf(AlternativePart::class, $body);
        $this->assertEquals('Text', $body->getParts()[0]->bodyToString());
        $this->assertEquals('<b>HTML</b>', $body->getParts()[1]->bodyToString());
    }

    public function testRenderFullOnly(): void
    {
        $email = $this->prepareEmail(<<<EOF
{% block subject %}Subject{% endblock %}
{% block text %}Text{% endblock %}
{% block html %}<b>HTML</b>{% endblock %}
EOF
        , null, null);
        $body = $email->getBody();
        $this->assertInstanceOf(AlternativePart::class, $body);
        $this->assertEquals('Subject', $email->getSubject());
        $this->assertEquals('Text', $body->getParts()[0]->bodyToString());
        $this->assertEquals('<b>HTML</b>', $body->getParts()[1]->bodyToString());
    }

    public function testRenderFullOnlyWithTextOnly(): void
    {
        $email = $this->prepareEmail(<<<EOF
{% block text %}Text{% endblock %}
EOF
        , null, null);
        $body = $email->getBody();
        $this->assertInstanceOf(TextPart::class, $body);
        $this->assertEquals('', $email->getSubject());
        $this->assertEquals('Text', $body->bodyToString());
    }

    public function testRenderFullOnlyWithHtmlOnly(): void
    {
        $email = $this->prepareEmail(<<<EOF
{% block html %}<b>HTML</b>{% endblock %}
EOF
        , null, null);
        $body = $email->getBody();
        $this->assertInstanceOf(AlternativePart::class, $body);
        $this->assertEquals('', $email->getSubject());
        $this->assertEquals('HTML', $body->getParts()[0]->bodyToString());
        $this->assertEquals('<b>HTML</b>', $body->getParts()[1]->bodyToString());
    }

    public function testRenderFullAndText(): void
    {
        $email = $this->prepareEmail(<<<EOF
{% block text %}Text full{% endblock %}
{% block html %}<b>HTML</b>{% endblock %}
EOF
        , 'Text', null);
        $body = $email->getBody();
        $this->assertInstanceOf(AlternativePart::class, $body);
        $this->assertEquals('Text', $body->getParts()[0]->bodyToString());
        $this->assertEquals('<b>HTML</b>', $body->getParts()[1]->bodyToString());
    }

    public function testRenderFullAndHtml(): void
    {
        $email = $this->prepareEmail(<<<EOF
{% block text %}Text full{% endblock %}
{% block html %}<b>HTML</b>{% endblock %}
EOF
        , null, '<i>HTML</i>');
        $body = $email->getBody();
        $this->assertInstanceOf(AlternativePart::class, $body);
        $this->assertEquals('Text full', $body->getParts()[0]->bodyToString());
        $this->assertEquals('<i>HTML</i>', $body->getParts()[1]->bodyToString());
    }

    public function testRenderHtmlWithEmbeddedImages(): void
    {
        $email = $this->prepareEmail(null, null, '<img src="{{ email.image("image.jpg") }}" />');
        $body = $email->getBody();
        $this->assertInstanceOf(RelatedPart::class, $body);
        $this->assertInstanceOf(AlternativePart::class, $body->getParts()[0]);
        $this->assertStringMatchesFormat('<img src=3D"cid:%s@symfony" />', $body->getParts()[0]->getParts()[1]->bodyToString());
        $this->assertEquals('Some image data', base64_decode($body->getParts()[1]->bodyToString()));
    }

    public function testRenderFullWithAttachments(): void
    {
        $email = $this->prepareEmail(<<<EOF
{% block text %}Text{% endblock %}
{% block config %}
    {% do email.attach('document.txt') %}
{% endblock %}
EOF
        , null, null);
        $body = $email->getBody();
        $this->assertInstanceOf(MixedPart::class, $body);
        $this->assertEquals('Text', $body->getParts()[0]->bodyToString());
        $this->assertEquals('Some text document...', base64_decode($body->getParts()[1]->bodyToString()));
    }

    private function prepareEmail(?string $full, ?string $text, ?string $html): TemplatedEmail
    {
        $twig = new Environment(new ArrayLoader([
            'full' => $full,
            'text' => $text,
            'html' => $html,
            'document.txt' => 'Some text document...',
            'image.jpg' => 'Some image data',
        ]));
        $renderer = new Renderer($twig);
        $email = (new TemplatedEmail())->to('fabien@symfony.com')->from('helene@symfony.com');
        if (null !== $full) {
            $email->template('full');
        }
        if (null !== $text) {
            $email->textTemplate('text');
        }
        if (null !== $html) {
            $email->htmlTemplate('html');
        }

        return $renderer->render($email);
    }
}
