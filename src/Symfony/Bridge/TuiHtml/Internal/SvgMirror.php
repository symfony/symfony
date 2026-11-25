<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Internal;

use Symfony\Bridge\TuiHtml\Widget\SvgWidget;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;
use Symfony\Component\HtmlRenderer\Vector\SvgStyle;
use Symfony\Component\Tui\Widget\AbstractWidget;

/** @internal */
final class SvgMirror
{
    private \WeakMap $registered;
    private \WeakMap $styles;
    private ?\WeakReference $root = null;

    public function __construct()
    {
        $this->registered = new \WeakMap();
        $this->styles = new \WeakMap();
    }

    public function register(SvgWidget $widget, \Dom\Element $source): void
    {
        foreach (($this->registered[$widget] ?? [])['nodes'] ?? [] as [$previous]) {
            unset($this->styles[$previous]);
        }
        $attributes = [];
        foreach ($source->attributes as $attribute) {
            $attributes[$attribute->name] = $attribute->value;
        }
        $this->registered[$widget] = ['source' => $source, 'fingerprint' => null, 'active' => false, 'mirror' => null, 'nodes' => [], 'attributes' => $attributes, 'classes' => $source->getAttribute('class') ?? '', 'styles' => null];
    }

    public function contains(AbstractWidget $widget): bool
    {
        if (!($this->registered[$widget]['active'] ?? false) || null === $root = $this->root?->get()) {
            return false;
        }
        for ($parent = $widget; null !== $parent; $parent = $parent->getParent()) {
            if ($parent === $root) {
                return true;
            }
        }

        return false;
    }

    public function scan(AbstractWidget $root): bool
    {
        $this->root = \WeakReference::create($root);
        $changed = false;
        foreach ($this->registered as $widget => $entry) {
            $active = false;
            for ($parent = $widget; null !== $parent; $parent = $parent->getParent()) {
                if ($parent === $root) {
                    $active = true;
                    break;
                }
            }
            if (!$active) {
                if ($entry['active']) {
                    foreach ($entry['nodes'] as [$source]) {
                        unset($this->styles[$source]);
                    }
                    $entry['mirror']?->replaceChildren();
                    $entry['mirror'] = null;
                    $entry['nodes'] = [];
                    $entry['children'] = [];
                    $entry['styles'] = null;
                    $changed = true;
                }
            } else {
                $fingerprint = self::fingerprint($entry['source']);
                if (!$entry['active'] || $entry['fingerprint'] !== $fingerprint) {
                    $entry['fingerprint'] = $fingerprint;
                    $entry['dirty'] = true;
                    $widget->invalidateSource();
                    $changed = true;
                }
            }
            $entry['active'] = $active;
            $this->registered[$widget] = $entry;
        }

        return $changed;
    }

    public function sync(array $elements): bool
    {
        $changed = false;
        foreach ($this->registered as $widget => $entry) {
            if (!$entry['active'] || null === $mirror = $elements[spl_object_id($widget)] ?? null) {
                continue;
            }
            if (($entry['dirty'] ?? false) || $entry['mirror'] !== $mirror) {
                foreach ($entry['nodes'] as [$source]) {
                    unset($this->styles[$source]);
                }
                $entry['nodes'] = [[$entry['source'], $mirror]];
                $children = [];
                foreach ($entry['source']->childNodes as $source) {
                    $clone = $mirror->ownerDocument->importNode($source, true);
                    $children[] = $clone;
                    $pending = [[$source, $clone]];
                    while ($pending) {
                        [$original, $copy] = array_pop($pending);
                        if ($original instanceof \Dom\Element) {
                            foreach (iterator_to_array($copy->attributes) as $attribute) {
                                if (\in_array($attribute->name, ['data-tui-oid', 'data-tui-inline', 'data-tui-part'], true)) {
                                    $copy->removeAttribute($attribute->name);
                                }
                            }
                            $entry['nodes'][] = [$original, $copy];
                        }
                        $sourceChild = $original->firstChild;
                        for ($copyChild = $copy->firstChild; null !== $copyChild; $copyChild = $copyChild->nextSibling) {
                            $pending[] = [$sourceChild, $copyChild];
                            $sourceChild = $sourceChild->nextSibling;
                        }
                    }
                }
                $entry['children'] = $children;
                $entry['mirror'] = $mirror;
                $entry['dirty'] = false;
                $entry['styles'] = null;
                $changed = true;
            }
            if (!$entry['source']->hasAttribute('class') && '' !== $entry['classes']) {
                $classes = array_diff(preg_split('/\s+/', trim($mirror->getAttribute('class') ?? '')), preg_split('/\s+/', trim(($entry['attributes']['class'] ?? '').' '.$entry['classes'])));
                $mirror->setAttribute('class', implode(' ', array_filter($classes)));
                $entry['classes'] = '';
                $changed = true;
            }
            foreach ($entry['attributes'] as $name => $value) {
                if (!$entry['source']->hasAttribute($name) && $mirror->getAttribute($name) === $value) {
                    $mirror->removeAttribute($name);
                    $changed = true;
                }
            }
            foreach ($entry['source']->attributes as $attribute) {
                if (\in_array($attribute->name, ['data-tui-oid', 'data-tui-inline', 'data-tui-part'], true)) {
                    continue;
                }
                $value = $attribute->value;
                if ('class' === $attribute->name) {
                    $classes = array_diff(preg_split('/\s+/', trim($mirror->getAttribute('class') ?? '')), preg_split('/\s+/', trim(($entry['attributes']['class'] ?? '').' '.$entry['classes'])));
                    $entry['classes'] = $value;
                    $value = implode(' ', array_unique(array_filter([...$classes, ...preg_split('/\s+/', trim($value))])));
                } elseif (($entry['attributes'][$attribute->name] ?? null) === $value) {
                    continue;
                }
                if ($mirror->getAttribute($attribute->name) !== $value) {
                    $mirror->setAttributeNS($attribute->namespaceURI, $attribute->name, $value);
                    $changed = true;
                }
            }
            if (iterator_to_array($mirror->childNodes) !== ($entry['children'] ?? [])) {
                $mirror->replaceChildren(...$entry['children']);
                $changed = true;
            }
            $this->registered[$widget] = $entry;
        }

        return $changed;
    }

    public function capture(\SplObjectStorage $styles, StyleResolver $resolver): void
    {
        foreach ($this->registered as $widget => $entry) {
            if (!$entry['active']) {
                continue;
            }
            $values = [];
            foreach ($entry['nodes'] as [$source, $mirror]) {
                if (isset($styles[$mirror])) {
                    $computed = self::values($styles[$mirror], $resolver->getCascadeRecord($mirror));
                    $this->styles[$source] = [\WeakReference::create($widget), $computed];
                    $values[] = $computed;
                }
            }
            $fingerprint = hash('sha256', serialize($values));
            if ($entry['styles'] !== $fingerprint) {
                $widget->invalidateSource();
                $entry['styles'] = $fingerprint;
                $this->registered[$widget] = $entry;
            }
        }
    }

    public function get(\Dom\Element $source): ?array
    {
        if (null === $entry = $this->styles[$source] ?? null) {
            return null;
        }
        $widget = $entry[0]->get();

        return null !== $widget && $this->contains($widget) ? $entry[1] : null;
    }

    public static function fingerprint(\Dom\Node $root): string
    {
        $count = $bytes = 0;
        $pending = [[$root, 0]];
        while ($pending) {
            [$node, $depth] = array_pop($pending);
            if (++$count > 20000 || $depth > 512) {
                throw new \LengthException('An SVG style snapshot cannot exceed 20000 nodes or 512 levels.');
            }
            if ($node instanceof \Dom\Element) {
                $bytes += \strlen($node->nodeName) * 2 + 5;
                foreach ($node->attributes as $attribute) {
                    $bytes += \strlen($attribute->name) + \strlen($attribute->value) + 4;
                }
            } else {
                $bytes += \strlen($node->nodeValue ?? '');
            }
            if ($bytes > 8 * 1024 * 1024) {
                throw new \LengthException('An SVG style snapshot cannot exceed 8 MiB.');
            }
            if ($node !== $root && null !== $node->nextSibling) {
                $pending[] = [$node->nextSibling, $depth];
            }
            if (null !== $node->firstChild) {
                $pending[] = [$node->firstChild, $depth + 1];
            }
        }
        $document = $root instanceof \Dom\Document ? $root : $root->ownerDocument;
        if (!$document instanceof \Dom\HTMLDocument && !$document instanceof \Dom\XMLDocument) {
            throw new \LogicException('An SVG snapshot requires a native HTML or XML document.');
        }
        $serialized = $root instanceof \Dom\Document ? $document->saveXml() : $document->saveXml($root);
        if (\strlen($serialized) > 8 * 1024 * 1024) {
            throw new \LengthException('An SVG style snapshot cannot exceed 8 MiB.');
        }

        return hash('sha256', $serialized);
    }

    public static function values(array $computed, array $record): array
    {
        $values = SvgStyle::computedValues($computed);
        foreach ($record['cascaded'] as $property => $value) {
            if (isset($values[$property]) && !str_contains($value, '(') && !\in_array(strtolower(trim($value)), ['initial', 'inherit', 'unset', 'revert', 'revert-layer'], true)) {
                $values[$property] = $value;
            }
        }

        return $values;
    }
}
