<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Mime;

use League\HTMLToMarkdown\HtmlConverter;
use Twig\Environment;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 4.3
 */
final class Renderer
{
    private $twig;
    private $context;
    private $converter;

    public function __construct(Environment $twig, array $context = [])
    {
        $this->twig = $twig;
        $this->context = $context;
        if (class_exists(HtmlConverter::class)) {
            $this->converter = new HtmlConverter([
                'hard_break' => true,
                'strip_tags' => true,
                'remove_nodes' => 'head style',
            ]);
        }
    }

    public function render(TemplatedEmail $email): TemplatedEmail
    {
        $email = clone $email;

        $vars = array_merge($this->context, $email->getContext(), [
            'email' => new WrappedTemplatedEmail($this->twig, $email),
        ]);

        if ($template = $email->getTemplate()) {
            $this->renderFull($email, $template, $vars);
        }

        if ($template = $email->getTextTemplate()) {
            $email->text($this->twig->render($template, $vars));
        }

        if ($template = $email->getHtmlTemplate()) {
            $email->html($this->twig->render($template, $vars));
        }

        // if text body is empty, compute one from the HTML body
        if (!$email->getTextBody() && null !== $html = $email->getHtmlBody()) {
            $email->text($this->convertHtmlToText(\is_resource($html) ? stream_get_contents($html) : $html));
        }

        return $email;
    }

    private function renderFull(TemplatedEmail $email, string $template, array $vars): void
    {
        $template = $this->twig->load($template);

        if ($template->hasBlock('subject', $vars)) {
            $email->subject($template->renderBlock('subject', $vars));
        }

        if ($template->hasBlock('text', $vars)) {
            $email->text($template->renderBlock('text', $vars));
        }

        if ($template->hasBlock('html', $vars)) {
            $email->html($template->renderBlock('html', $vars));
        }

        if ($template->hasBlock('config', $vars)) {
            // we discard the output as we're only interested
            // in the side effect of calling email methods
            $template->renderBlock('config', $vars);
        }
    }

    private function convertHtmlToText(string $html): string
    {
        if (null !== $this->converter) {
            return $this->converter->convert($html);
        }

        return strip_tags($html);
    }
}
