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

use Symfony\Component\HtmlRenderer\Form\ControlValue;
use Symfony\Component\HtmlRenderer\Form\NumericControl;
use Symfony\Component\HtmlRenderer\Form\SelectOptions;
use Symfony\Component\HtmlRenderer\Html\EmbeddedContent;
use Symfony\Component\HtmlRenderer\RenderSession;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Widget\Util\StringUtils;

/** @internal */
final class ControlStyles
{
    private \SplObjectStorage $roles;
    private \SplObjectStorage $frames;

    public function __construct()
    {
        $this->roles = new \SplObjectStorage();
        $this->frames = new \SplObjectStorage();
    }

    /** @param list<array{\Dom\Element, ?\Dom\Element}> $elements */
    public function synchronize(RenderSession $session, bool $enabled, array $elements): void
    {
        $next = new \SplObjectStorage();
        $active = new \SplObjectStorage();
        foreach ($elements as [$element]) {
            $active[$element] = true;
        }
        foreach ($enabled ? $elements : [] as [$element]) {
            if (!$session->containsElement($element)) {
                continue;
            }
            $type = strtolower($element->getAttribute('type') ?? '');
            if (!\in_array($element->localName, ['progress', 'meter', 'select'], true) && !('input' === $element->localName && \in_array($type, ['range', 'color', 'file'], true))) {
                continue;
            }
            if ('select' === $element->localName) {
                $listBox = SelectOptions::isListBox($element);
                $session->setState($element, 'tui-dropdown', !$listBox);
                if (!$session->containsElement($element)) {
                    continue;
                }
                if ($listBox) {
                    continue;
                }
                $styles = ['before' => ['content' => '"[ "'], 'picker-icon' => ['content' => '"▾"', 'margin-left' => '1ch'], 'after' => ['content' => '" ]"']];
            } elseif ('input' === $element->localName && \in_array($type, ['color', 'file'], true)) {
                $styles = self::inputStyles($session, $element, $type);
                if (!$styles) {
                    continue;
                }
            } else {
                $model = match ($element->localName) {
                    'progress' => NumericControl::progress($element),
                    'meter' => NumericControl::meter($element),
                    default => NumericControl::range($element, $session->getValue($element)),
                };
                $presentation = match ($element->localName) {
                    'progress' => NumericControlPresentation::progress($model),
                    'meter' => NumericControlPresentation::meter($model),
                    default => NumericControlPresentation::range($model),
                };
                $label = $presentation['label'];
                if ('' === $label) {
                    continue;
                }
                $columns = \strlen($label);
                $range = 'input' === $element->localName;
                $track = $range ? 'track' : 'bar';
                $fraction = json_encode($presentation['fraction'] ?? $model['fraction'], \JSON_THROW_ON_ERROR);
                $content = json_encode($label, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
                $styles = [
                    '' => ['overflow' => 'hidden'],
                    'after' => ['content' => $content, 'position' => 'absolute', 'right' => '0', 'top' => '0', 'width' => '100%', 'text-align' => 'right', 'white-space' => 'pre'],
                    $track => ['width' => 'calc(100% - '.$columns.'ch)'],
                    $track.'-fill' => ['width' => 'calc((100% - '.$columns.'ch) * '.$fraction.')'],
                ];
                if ($range) {
                    $styles['thumb'] = ['left' => 'calc((100% - '.$columns.'ch) * '.$fraction.' - .5em)'];
                }
            }
            foreach ($styles as $role => $properties) {
                if (!$session->containsElement($element)) {
                    continue 2;
                }
                $session->setUserAgentStyle($element, $properties, '' === $role ? null : $role);
            }
            if ($session->containsElement($element)) {
                $next[$element] = array_keys($styles);
            }
        }
        foreach ($this->roles as $element) {
            if (!$enabled && 'select' === $element->localName && isset($active[$element]) && $session->containsElement($element)) {
                $session->setState($element, 'tui-dropdown', false);
            }
            foreach ($this->roles[$element] as $role) {
                if (isset($active[$element]) && $session->containsElement($element) && !\in_array($role, $next[$element] ?? [], true)) {
                    $session->setUserAgentStyle($element, [], '' === $role ? null : $role);
                }
            }
        }
        $this->roles = $next;
    }

    /** @param list<array{\Dom\Element, ?RenderSession}> $frames */
    public function synchronizeFrames(RenderSession $session, array $frames, bool $enabled): void
    {
        $next = new \SplObjectStorage();
        $active = new \SplObjectStorage();
        foreach ($frames as [$frame]) {
            $active[$frame] = true;
        }
        foreach ($enabled ? $frames : [] as [$frame, $child]) {
            if (!$session->containsElement($frame) || null !== $child) {
                continue;
            }
            $alternative = trim(preg_replace('/\s+/u', ' ', StringUtils::stripControlBytes(AnsiUtils::stripAnsiCodes(EmbeddedContent::alternative($frame)))) ?? '');
            if ('' === $alternative) {
                continue;
            }
            $session->setUserAgentStyle($frame, ['display' => 'inline', 'width' => 'auto', 'height' => 'auto', 'border-width' => '0']);
            if (!$session->containsElement($frame)) {
                continue;
            }
            $session->setUserAgentStyle($frame, ['content' => json_encode('['.$alternative.']', \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)], 'before');
            if ($session->containsElement($frame)) {
                $next[$frame] = true;
            }
        }
        foreach ($this->frames as $frame) {
            if (!isset($next[$frame]) && isset($active[$frame]) && $session->containsElement($frame)) {
                $session->setUserAgentStyle($frame, []);
                if ($session->containsElement($frame)) {
                    $session->setUserAgentStyle($frame, [], 'before');
                }
            }
        }
        $this->frames = $next;
    }

    private static function inputStyles(RenderSession $session, \Dom\Element $element, string $type): array
    {
        $value = $session->getValue($element);
        if ('color' === $type) {
            $swatch = ControlValue::colorSwatch($value);

            return ['before' => ['content' => json_encode(null === $swatch ? '░░ ' : '██ ', \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR), 'color' => $swatch ?? 'inherit', 'opacity' => null === $swatch ? '.5' : '1']];
        }
        if ('' !== $value || $element->hasAttribute('placeholder')) {
            return [];
        }
        $label = $element->hasAttribute('multiple') ? 'Paths to files' : 'Path to a file';

        return [
            '' => ['position' => 'relative'],
            'after' => ['content' => json_encode($label, \JSON_THROW_ON_ERROR), 'position' => 'absolute', 'left' => '2ch', 'top' => '0', 'white-space' => 'pre', 'pointer-events' => 'none', 'color' => 'inherit', 'opacity' => '.5'],
        ];
    }
}
