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
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Message;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class BodyRenderer implements BodyRendererInterface
{
    private $twig;
    private $context;
    private $converter;
    private $translator;

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

    /**
     * @return $this
     */
    public function setTranslator(TranslatorInterface $translator)
    {
        $this->translator = $translator;

        return $this;
    }

    public function render(Message $message): void
    {
        if (!$message instanceof TemplatedEmail) {
            return;
        }

        $messageContext = $message->getContext();
        if (isset($messageContext['email'])) {
            throw new InvalidArgumentException(sprintf('A "%s" context cannot have an "email" entry as this is a reserved variable.', \get_class($message)));
        }

        $vars = array_merge($this->context, $messageContext, [
            'email' => new WrappedTemplatedEmail($this->twig, $message),
        ]);

        $previousLocale = null;
        if (null !== $message->getLocale() &&
            null !== $this->translator &&
            method_exists($this->translator, 'getLocale')
            && method_exists($this->translator, 'setLocale')
        ) {
            $previousLocale = $this->translator->getLocale();
            $this->translator->setLocale($message->getLocale());
        }

        if ($template = $message->getTextTemplate()) {
            $message->text($this->twig->render($template, $vars));
        }

        if ($template = $message->getHtmlTemplate()) {
            $message->html($this->twig->render($template, $vars));
        }

        if ($previousLocale) {
            $this->translator->setLocale($previousLocale);
        }

        // if text body is empty, compute one from the HTML body
        if (!$message->getTextBody() && null !== $html = $message->getHtmlBody()) {
            $message->text($this->convertHtmlToText(\is_resource($html) ? stream_get_contents($html) : $html));
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
