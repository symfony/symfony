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

use League\HTMLToMarkdown\HtmlConverterInterface;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\HtmlToTextConverter\DefaultHtmlToTextConverter;
use Symfony\Component\Mime\HtmlToTextConverter\HtmlToTextConverterInterface;
use Symfony\Component\Mime\HtmlToTextConverter\LeagueHtmlToMarkdownConverter;
use Symfony\Component\Mime\Message;
use Twig\Environment;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class BodyRenderer implements BodyRendererInterface
{
    private Environment $twig;
    private array $context;
    private HtmlToTextConverterInterface $converter;

    public function __construct(Environment $twig, array $context = [], HtmlToTextConverterInterface $converter = null)
    {
        $this->twig = $twig;
        $this->context = $context;
        $this->converter = $converter ?: (interface_exists(HtmlConverterInterface::class) ? new LeagueHtmlToMarkdownConverter() : new DefaultHtmlToTextConverter());
    }

    public function render(Message $message): void
    {
        if (!$message instanceof TemplatedEmail) {
            return;
        }

        if (null === $message->getTextTemplate() && null === $message->getHtmlTemplate()) {
            // email has already been rendered
            return;
        }

        $messageContext = $message->getContext();

        if (isset($messageContext['email'])) {
            throw new InvalidArgumentException(sprintf('A "%s" context cannot have an "email" entry as this is a reserved variable.', get_debug_type($message)));
        }

        $vars = array_merge($this->context, $messageContext, [
            'email' => new WrappedTemplatedEmail($this->twig, $message),
        ]);

        if ($template = $message->getTextTemplate()) {
            $message->text($this->twig->render($template, $vars));
        }

        if ($template = $message->getHtmlTemplate()) {
            $message->html($this->twig->render($template, $vars));
        }

        $message->markAsRendered();

        // if text body is empty, compute one from the HTML body
        if (!$message->getTextBody() && null !== $html = $message->getHtmlBody()) {
            $text = $this->converter->convert(\is_resource($html) ? stream_get_contents($html) : $html, $message->getHtmlCharset());
            $message->text($text, $message->getHtmlCharset());
        }
    }
}
