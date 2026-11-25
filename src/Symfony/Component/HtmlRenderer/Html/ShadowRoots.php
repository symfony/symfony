<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Html;

use Symfony\Component\HtmlRenderer\Css\Selector;

/**
 * Composes declarative shadow content on an owned rendering document.
 *
 * Callers retaining a semantic source document must compose a separate native DOM mirror.
 *
 * @internal
 */
final class ShadowRoots
{
    private \SplObjectStorage $fragments;

    public function __construct(private readonly int $maxBytes = 4 * 1024 * 1024)
    {
        if ($maxBytes < 1) {
            throw new \InvalidArgumentException('The shadow content byte limit must be positive.');
        }
        $this->fragments = new \SplObjectStorage();
    }

    /**
     * @return array{document: \Dom\HTMLDocument, owners: \SplObjectStorage, mirrors: \SplObjectStorage, parents: \SplObjectStorage, roots: \SplObjectStorage, depths: array<int, int>, fingerprint: string}
     */
    public function createView(\Dom\HTMLDocument $document): array
    {
        $this->prune($document);
        $mirror = clone $document;
        $owners = new \SplObjectStorage();
        self::mapTree($document, $mirror, $owners, 0);
        $nextCache = new \SplObjectStorage();
        $roots = new \SplObjectStorage();
        $bytes = 0;
        $depths = self::compose($mirror, function (\Dom\Element $template, \Dom\HTMLDocument $target) use ($document, $owners, $nextCache, $roots, &$bytes): \Dom\DocumentFragment {
            $source = $owners[$template];
            $host = $owners[$template->parentElement];
            $depth = self::depthOf($template->parentElement) + 1;
            $markup = $source->innerHTML;
            $entry = $this->fragments[$source] ?? null;
            $cached = null !== $entry && $entry['markup'] === $markup;
            $html = $cached ? $document->saveHtml($entry['root']) : $markup;
            $size = \strlen($html);
            if ($size > $this->maxBytes - $bytes) {
                throw new \LengthException('The shadow content exceeds the aggregate parsed byte limit.');
            }
            $bytes += $size;
            if (!$cached) {
                $container = $document->createElement('div');
                $container->innerHTML = $markup;
                if (\PHP_VERSION_ID < 80415) {
                    IdAttributes::mark($container);
                }
                $root = $document->createDocumentFragment();
                while (null !== $child = $container->firstChild) {
                    $root->appendChild($child);
                }
                $entry = ['markup' => $markup, 'root' => $root];
                $html = $document->saveHtml($root);
                $extra = max(0, \strlen($html) - $size);
                if ($extra > $this->maxBytes - $bytes) {
                    throw new \LengthException('The shadow content exceeds the aggregate parsed byte limit.');
                }
                $bytes += $extra;
            }
            $nextCache[$source] = $entry;
            $roots[$host] = $entry['root'];
            $content = $target->createDocumentFragment();
            foreach ($entry['root']->childNodes as $child) {
                $copy = $target->importNode($child, true);
                $content->appendChild($copy);
                self::mapTree($child, $copy, $owners, $depth);
            }

            return $content;
        });
        $activeOwners = new \SplObjectStorage();
        $mirrors = new \SplObjectStorage();
        $parents = new \SplObjectStorage();
        $walk = static function (\Dom\Node $node, int $depth) use (&$walk, $owners, $activeOwners, $mirrors, $parents): void {
            if ($depth > 512) {
                throw new \LengthException('The composed document exceeds the depth limit.');
            }
            $source = $owners[$node];
            $activeOwners[$node] = $source;
            $mirrors[$source] = $node;
            $parents[$source] = null !== $node->parentNode ? $owners[$node->parentNode] : null;
            foreach ($node->childNodes as $child) {
                $walk($child, $depth + 1);
            }
        };
        $walk($mirror, 0);
        $activeTrees = [];
        foreach (iterator_to_array($roots, false) as $host) {
            if (!isset($mirrors[$host])) {
                unset($roots[$host]);
                continue;
            }
            $activeTrees[(int) $mirrors[$host]->getAttribute(Selector::HOST_ATTRIBUTE)] = true;
        }
        foreach (iterator_to_array($nextCache, false) as $template) {
            if (null === $template->parentElement || !isset($roots[$template->parentElement])) {
                unset($nextCache[$template]);
            }
        }
        $this->fragments = $nextCache;

        return ['document' => $mirror, 'owners' => $activeOwners, 'mirrors' => $mirrors, 'parents' => $parents, 'roots' => $roots, 'depths' => array_intersect_key($depths, $activeTrees), 'fingerprint' => $this->getFingerprint()];
    }

    /** Verifies current native hosting, independently of a possibly older composed view. */
    public function isActiveNode(\Dom\Node $node, \Dom\HTMLDocument $document): bool
    {
        $seen = [];
        for ($level = 0; $level <= 256; ++$level) {
            if ($node->ownerDocument !== $document) {
                return false;
            }
            $root = $node;
            for ($depth = 0; null !== $root->parentNode && $depth <= 512; ++$depth) {
                $root = $root->parentNode;
            }
            if ($root === $document) {
                return $node->isConnected;
            }
            if (isset($seen[spl_object_id($root)])) {
                return false;
            }
            $seen[spl_object_id($root)] = true;
            $host = null;
            foreach ($this->fragments as $template) {
                $entry = $this->fragments[$template];
                if ($entry['root'] !== $root) {
                    continue;
                }
                $host = $template->parentElement;
                if (null === $host || $template->ownerDocument !== $document || !self::canHostShadowRoot($host) || $host->hasAttribute(Selector::HOST_ATTRIBUTE) || !\in_array(strtolower(trim($template->getAttribute('shadowrootmode') ?? '')), ['open', 'closed'], true) || $template->innerHTML !== $entry['markup']) {
                    return false;
                }
                for ($sibling = $host->firstElementChild; null !== $sibling && $sibling !== $template; $sibling = $sibling->nextElementSibling) {
                    if ('template' === $sibling->localName && \in_array(strtolower(trim($sibling->getAttribute('shadowrootmode') ?? '')), ['open', 'closed'], true)) {
                        return false;
                    }
                }
                break;
            }
            if (null === $host) {
                return false;
            }
            $node = $host;
        }

        return false;
    }

    public function getFingerprint(): string
    {
        $html = [];
        $bytes = 0;
        foreach ($this->fragments as $template) {
            $root = $this->fragments[$template]['root'];
            $source = $root->ownerDocument->saveHtml($root);
            if (\strlen($source) > $this->maxBytes - $bytes) {
                throw new \LengthException('The shadow content exceeds the aggregate parsed byte limit.');
            }
            $bytes += \strlen($source);
            $html[] = $source;
        }

        return hash('xxh128', serialize($html));
    }

    /**
     * @param \Closure(\Dom\Element, \Dom\HTMLDocument): \Dom\DocumentFragment|null $materialize
     *
     * @return array<int, int> How deep each tree is in others, from 1
     */
    public static function compose(\Dom\HTMLDocument $document, ?\Closure $materialize = null): array
    {
        $trees = 0;
        $depths = [];
        while ($trees < 256 && null !== $template = $document->querySelector('template[shadowrootmode]')) {
            $host = $template->parentElement;
            $mode = strtolower(trim($template->getAttribute('shadowrootmode') ?? ''));
            $template->removeAttribute('shadowrootmode');
            // open or closed, on an element that can have a shadow root; only the first of a host: the others are
            // templates, not drawn
            if (null === $host || !\in_array($mode, ['open', 'closed'], true) || $host->hasAttribute(Selector::HOST_ATTRIBUTE) || !self::canHostShadowRoot($host)) {
                continue;
            }
            for ($other = $host->firstElementChild; null !== $other; $other = $other->nextElementSibling) {
                $other->removeAttribute('shadowrootmode');
            }
            $tree = ++$trees;
            $depths[$tree] = 1 + ($depths[(int) $host->getAttribute(Selector::TREE_ATTRIBUTE)] ?? 0);
            $host->setAttribute(Selector::HOST_ATTRIBUTE, (string) $tree);
            // The content of a template is outside its native child tree.
            $content = $document->createElement('div');
            if (null === $materialize) {
                $content->innerHTML = $template->innerHTML;
            } else {
                $content->appendChild($materialize($template, $document));
            }
            if (\PHP_VERSION_ID < 80415) {
                IdAttributes::mark($content);
            }
            foreach ($content->querySelectorAll('*') as $element) {
                $element->setAttribute(Selector::TREE_ATTRIBUTE, (string) $tree);
            }
            $slots = [];
            foreach ($content->querySelectorAll('slot') as $slot) {
                $slots[$slot->getAttribute('name') ?? ''] ??= $slot;
            }
            $assigned = [];
            foreach (iterator_to_array($host->childNodes, false) as $child) {
                $host->removeChild($child);
                $name = $child instanceof \Dom\Element ? $child->getAttribute('slot') ?? '' : '';
                if ($child !== $template && ($child instanceof \Dom\Element || $child instanceof \Dom\Text) && isset($slots[$name])) {
                    $assigned[$name][] = $child;
                }
            }
            foreach ($assigned as $name => $nodes) {
                $slot = $slots[$name];
                while (null !== $slot->firstChild) {
                    $slot->removeChild($slot->firstChild);
                }
                foreach ($nodes as $node) {
                    if ($node instanceof \Dom\Element) {
                        $node->setAttribute(Selector::SLOTTED_ATTRIBUTE, (string) $tree);
                    }
                    $slot->appendChild($node);
                }
            }
            foreach (iterator_to_array($content->childNodes, false) as $node) {
                $host->appendChild($node);
            }
        }
        self::exposeParts($document, $depths);

        return $depths;
    }

    private function prune(\Dom\HTMLDocument $document): void
    {
        if (0 === \count($this->fragments)) {
            return;
        }
        $valid = new \SplObjectStorage();
        $hosts = new \SplObjectStorage();
        foreach ($this->fragments as $template) {
            $entry = $this->fragments[$template];
            $host = $template->parentElement;
            if ($template->ownerDocument !== $document || null === $host || !self::canHostShadowRoot($host) || $host->hasAttribute(Selector::HOST_ATTRIBUTE) || !\in_array(strtolower(trim($template->getAttribute('shadowrootmode') ?? '')), ['open', 'closed'], true) || $entry['markup'] !== $template->innerHTML) {
                continue;
            }
            $first = null;
            for ($child = $host->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
                if ('template' === $child->localName && \in_array(strtolower(trim($child->getAttribute('shadowrootmode') ?? '')), ['open', 'closed'], true)) {
                    $first = $child;
                    break;
                }
            }
            if ($first === $template) {
                $valid[$template] = $entry;
                $hosts[$entry['root']] = $host;
            }
        }
        $connected = static function (\Dom\Node $node, array $seen = []) use (&$connected, $document, $hosts): bool {
            if ($node->ownerDocument !== $document) {
                return false;
            }
            if ($node->isConnected) {
                return true;
            }
            $root = $node->getRootNode();
            if (!$root instanceof \Dom\DocumentFragment || !isset($hosts[$root]) || \in_array($root, $seen, true)) {
                return false;
            }

            return $connected($hosts[$root], [...$seen, $root]);
        };
        foreach (iterator_to_array($valid, false) as $template) {
            if (!$connected($template)) {
                unset($valid[$template]);
            }
        }
        $this->fragments = $valid;
    }

    private static function depthOf(\Dom\Node $node): int
    {
        $depth = 0;
        for ($parent = $node->parentNode; null !== $parent; $parent = $parent->parentNode) {
            if (++$depth > 512) {
                throw new \LengthException('The composed document exceeds the depth limit.');
            }
        }

        return $depth;
    }

    private static function mapTree(\Dom\Node $source, \Dom\Node $mirror, \SplObjectStorage $owners, int $depth): void
    {
        if ($depth > 512) {
            throw new \LengthException('The composed document exceeds the depth limit.');
        }
        $owners[$mirror] = $source;
        foreach ($source->childNodes as $index => $child) {
            self::mapTree($child, $mirror->childNodes->item($index), $owners, $depth + 1);
        }
    }

    private static function exposeParts(\Dom\HTMLDocument $document, array $depths): void
    {
        $hosts = [];
        foreach ($document->querySelectorAll('['.Selector::HOST_ATTRIBUTE.']') as $host) {
            $hosts[(int) $host->getAttribute(Selector::HOST_ATTRIBUTE)] = $host;
        }
        $context = static fn (\Dom\Element $element): int => (int) ($element->getAttribute(Selector::TREE_ATTRIBUTE) ?? 0);
        $add = static function (\Dom\Element $element, int $context, array $names): void {
            $attribute = Selector::PART_ATTRIBUTE.$context;
            $element->setAttribute($attribute, implode(' ', array_unique([...preg_split('/\s+/', trim($element->getAttribute($attribute) ?? ''), -1, \PREG_SPLIT_NO_EMPTY) ?: [], ...$names])));
        };
        // Its own parts, as the tree of its host sees them
        foreach ($document->querySelectorAll('['.Selector::TREE_ATTRIBUTE.'][part]') as $element) {
            $tree = (int) $element->getAttribute(Selector::TREE_ATTRIBUTE);
            if (isset($hosts[$tree]) && [] !== $names = preg_split('/\s+/', trim((string) $element->getAttribute('part')), -1, \PREG_SPLIT_NO_EMPTY) ?: []) {
                $add($element, $context($hosts[$tree]), $names);
            }
        }
        // Those a host in a tree exports, the deepest first, so that they go on out
        arsort($depths);
        foreach (array_keys($depths) as $tree) {
            $host = $hosts[$tree] ?? null;
            if (null === $host || null === ($exports = $host->getAttribute('exportparts')) || 0 === $inner = $context($host)) {
                continue;
            }
            $outer = $context($hosts[$inner] ?? $host);
            $renames = [];
            foreach (explode(',', $exports) as $export) {
                $pair = array_map('trim', explode(':', $export, 2));
                if ('' !== $pair[0]) {
                    $renames[$pair[0]][] = '' !== ($pair[1] ?? '') ? $pair[1] : $pair[0];
                }
            }
            foreach ($host->querySelectorAll('['.Selector::PART_ATTRIBUTE.$inner.']') as $element) {
                $names = [];
                foreach (preg_split('/\s+/', trim((string) $element->getAttribute(Selector::PART_ATTRIBUTE.$inner)), -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $name) {
                    array_push($names, ...$renames[$name] ?? []);
                }
                if ($names) {
                    $add($element, $outer, $names);
                }
            }
        }
    }

    private static function canHostShadowRoot(\Dom\Element $element): bool
    {
        $name = strtolower($element->localName);

        return \in_array($name, ['article', 'aside', 'blockquote', 'body', 'div', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'main', 'nav', 'p', 'section', 'span'], true) || preg_match('/^[a-z][^\s\/>]*-[^\s\/>]*$/', $name) && !\in_array($name, ['annotation-xml', 'color-profile', 'font-face', 'font-face-src', 'font-face-uri', 'font-face-format', 'font-face-name', 'missing-glyph'], true);
    }
}
