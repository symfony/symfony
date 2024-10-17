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
     * @var ?DomVisitor
     */
    private ?DomVisitor $domVisitor = null;

    public function __construct(
        private HtmlSanitizerConfig $config,
        ?ParserInterface $parser = null,
    ) {
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
        $this->domVisitor ??= $this->createDomVisitor();

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
        $sanitized = $this->domVisitor->visit($context, $parsed)?->render() ?? '';

        return W3CReference::CONTEXT_DOCUMENT === $context ? '<!DOCTYPE html>'.$sanitized : $sanitized;
    }

    private function isValidUtf8(string $html): bool
    {
        // preg_match() fails silently on strings containing invalid UTF-8.
        return '' === $html || preg_match('//u', $html);
    }

    private function createDomVisitor(): DomVisitor
    {
        $elementsConfig = [];

        foreach ($this->config->getAllowedElements() as $allowedElement => $allowedAttributes) {
            $elementsConfig[$allowedElement] = $allowedAttributes;
        }

        foreach ($this->config->getBlockedElements() as $blockedElement => $v) {
            $elementsConfig[$blockedElement] = HtmlSanitizerAction::Block;
        }

        foreach ($this->config->getDroppedElements() as $droppedElement => $v) {
            $elementsConfig[$droppedElement] = HtmlSanitizerAction::Drop;
        }

        return new DomVisitor($this->config, $elementsConfig);
    }
}
