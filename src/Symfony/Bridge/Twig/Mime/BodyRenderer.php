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
use Twig\Environment;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class BodyRenderer implements BodyRendererInterface
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

    public function render(Message $message): void
    {
        if (!$message instanceof TemplatedEmail) {
            return;
        }

        $messageContext = $message->getContext();

        $previousRenderingKey = $messageContext[__CLASS__] ?? null;
        unset($messageContext[__CLASS__]);
        $currentRenderingKey = $this->getFingerPrint($message);
        if ($previousRenderingKey === $currentRenderingKey) {
            return;
        }

        if (isset($messageContext['email'])) {
            throw new InvalidArgumentException(sprintf('A "%s" context cannot have an "email" entry as this is a reserved variable.', \get_class($message)));
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

        // if text body is empty, compute one from the HTML body
        if (!$message->getTextBody() && null !== $html = $message->getHtmlBody()) {
            $message->text($this->convertHtmlToText(\is_resource($html) ? stream_get_contents($html) : $html));
        }
        $message->context($message->getContext() + [__CLASS__ => $currentRenderingKey]);
    }

    private function getFingerPrint(TemplatedEmail $message): string
    {
        $messageContext = $message->getContext();
        unset($messageContext[__CLASS__]);

        $payload = [$messageContext, $message->getTextTemplate(), $message->getHtmlTemplate()];
        try {
            $serialized = serialize($payload);
        } catch (\Exception $e) {
            // Serialization of 'Closure' is not allowed
            // Happens when context contain a closure, in that case, we assume that context always change.
            $serialized = random_bytes(8);
        }

        return md5($serialized);
    }

    private function convertHtmlToText(string $html): string
    {
        if (null !== $this->converter) {
            return $this->converter->convert($html);
        }

        return strip_tags(preg_replace('{<(head|style)\b.*?</\1>}is', '', $html));
    }
}
