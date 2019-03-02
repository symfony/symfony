<?php

namespace Symfony\Bridge\Twig\Tests\Mime;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class TemplatedEmailTest extends TestCase
{
    public function test()
    {
        $email = new TemplatedEmail();
        $email->context($context = ['product' => 'Symfony']);
        $this->assertEquals($context, $email->getContext());

        $email->template($template = 'full');
        $this->assertEquals($template, $email->getTemplate());

        $email->textTemplate($template = 'text');
        $this->assertEquals($template, $email->getTextTemplate());

        $email->htmlTemplate($template = 'html');
        $this->assertEquals($template, $email->getHtmlTemplate());
    }
}
