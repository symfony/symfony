<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Twig\Environment;
use Twig\Runtime\EscaperRuntime;

/**
 * Renders a form that posts hidden fields to an action with a submit button.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class PostFormRenderer
{
    /**
     * @param array{action: string, fields: array<string, string>, csrf_field: string|null} $form
     */
    public static function render(Environment $env, array $form, string|\Stringable $label, array $attr, array $formAttr, array $csrfFieldAttr): string
    {
        if ('' === $form['action']) {
            return '';
        }

        $escaper = $env->getRuntime(EscaperRuntime::class);
        $html = '<form'.self::renderAttributes($escaper, ['method' => 'post', 'action' => $form['action']] + $formAttr).'>';

        foreach ($form['fields'] as $name => $value) {
            $fieldAttr = ['type' => 'hidden', 'name' => $name, 'value' => $value];
            $html .= '<input'.self::renderAttributes($escaper, (string) $name === $form['csrf_field'] ? $fieldAttr + $csrfFieldAttr : $fieldAttr).'>';
        }

        return $html.'<button'.self::renderAttributes($escaper, ['type' => 'submit'] + $attr).'>'.$escaper->escape($label, 'html', null, true).'</button></form>';
    }

    private static function renderAttributes(EscaperRuntime $escaper, array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            if (false !== $value) {
                $html .= ' '.$escaper->escape((string) $name).'="'.$escaper->escape(true === $value ? $name : $value).'"';
            }
        }

        return $html;
    }
}
