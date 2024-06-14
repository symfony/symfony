<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer;

use Symfony\Component\HtmlSanitizer\Parser\MastermindsParser;
use Symfony\Component\HtmlSanitizer\Parser\ParserInterface;
use Symfony\Component\HtmlSanitizer\Reference\W3CReference;
use Symfony\Component\HtmlSanitizer\TextSanitizer\StringSanitizer;
use Symfony\Component\HtmlSanitizer\Visitor\DomVisitor;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
final class HtmlSanitizer implements HtmlSanitizerInterface
{
    private ParserInterface $parser;

    /**
     * @var array<string, DomVisitor>
     */
    private array $domVisitors = [];

    public function __construct(
        private HtmlSanitizerConfig $config,
        ?ParserInterface $parser = null,
    ) {
        $this->config = $config;
        $this->parser = $parser ?? new MastermindsParser();
    }

    public function sanitize(string $input): string
    {
        return $this->sanitizeWithContext(W3CReference::CONTEXT_BODY, $input);
    }

    public function sanitizeFor(string $element, string $input): string
    {
        return $this->sanitizeWithContext(
            W3CReference::CONTEXTS_MAP[StringSanitizer::htmlLower($element)] ?? W3CReference::CONTEXT_BODY,
            $input
        );
    }

    private function sanitizeWithContext(string $context, string $input): string
    {
        // Text context: early return with HTML encoding
        if (W3CReference::CONTEXT_TEXT === $context) {
            return StringSanitizer::encodeHtmlEntities($input);
        }

        // Other context: build a DOM visitor
        $this->domVisitors[$context] ??= $this->createDomVisitorForContext($context);

        // Prevent DOS attack induced by extremely long HTML strings
        if (-1 !== $this->config->getMaxInputLength() && \strlen($input) > $this->config->getMaxInputLength()) {
            $input = substr($input, 0, $this->config->getMaxInputLength());
        }

        // Only operate on valid UTF-8 strings. This is necessary to prevent cross
        // site scripting issues on Internet Explorer 6. Idea from Drupal (filter_xss).
        if (!$this->isValidUtf8($input)) {
            return '';
        }

        // Remove NULL character
        $input = str_replace(\chr(0), '', $input);

        // Parse as HTML
        if (!$parsed = $this->parser->parse($input)) {
            return '';
        }

        // Visit the DOM tree and render the sanitized nodes
        return $this->domVisitors[$context]->visit($parsed)?->render() ?? '';
    }

    private function isValidUtf8(string $html): bool
    {
        // preg_match() fails silently on strings containing invalid UTF-8.
        return '' === $html || preg_match('//u', $html);
    }

    private function createDomVisitorForContext(string $context): DomVisitor
    {
        $elementsConfig = [];

        // Head: only a few elements are allowed
        if (W3CReference::CONTEXT_HEAD === $context) {
            foreach ($this->config->getAllowedElements() as $allowedElement => $allowedAttributes) {
                if (\array_key_exists($allowedElement, W3CReference::HEAD_ELEMENTS)) {
                    $elementsConfig[$allowedElement] = $allowedAttributes;
                }
            }

            foreach ($this->config->getBlockedElements() as $blockedElement => $v) {
                if (\array_key_exists($blockedElement, W3CReference::HEAD_ELEMENTS)) {
                    $elementsConfig[$blockedElement] = HtmlSanitizerAction::Block;
                }
            }

            foreach ($this->config->getDroppedElements() as $droppedElement => $v) {
                if (\array_key_exists($droppedElement, W3CReference::HEAD_ELEMENTS)) {
                    $elementsConfig[$droppedElement] = HtmlSanitizerAction::Drop;
                }
            }

            return new DomVisitor($this->config, $elementsConfig);
        }

        // Body: allow any configured element that isn't in <head>
        foreach ($this->config->getAllowedElements() as $allowedElement => $allowedAttributes) {
            if (!\array_key_exists($allowedElement, W3CReference::HEAD_ELEMENTS)) {
                $elementsConfig[$allowedElement] = $allowedAttributes;
            }
        }

        foreach ($this->config->getBlockedElements() as $blockedElement => $v) {
            if (!\array_key_exists($blockedElement, W3CReference::HEAD_ELEMENTS)) {
                $elementsConfig[$blockedElement] = HtmlSanitizerAction::Block;
            }
        }

        foreach ($this->config->getDroppedElements() as $droppedElement => $v) {
            if (!\array_key_exists($droppedElement, W3CReference::HEAD_ELEMENTS)) {
                $elementsConfig[$droppedElement] = HtmlSanitizerAction::Drop;
            }
        }

        return new DomVisitor($this->config, $elementsConfig);
    }
}
