<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig;

use Symfony\Bundle\FullStack;
use Twig\Error\SyntaxError;

/**
 * @internal
 */
class UndefinedCallableHandler
{
    private static $filterComponents = [
        'humanize' => 'form',
        'trans' => 'translation',
        'transchoice' => 'translation',
        'yaml_encode' => 'yaml',
        'yaml_dump' => 'yaml',
    ];

    private static $functionComponents = [
        'asset' => 'asset',
        'asset_version' => 'asset',
        'dump' => 'debug-bundle',
        'expression' => 'expression-language',
        'form_widget' => 'form',
        'form_errors' => 'form',
        'form_label' => 'form',
        'form_help' => 'form',
        'form_row' => 'form',
        'form_rest' => 'form',
        'form' => 'form',
        'form_start' => 'form',
        'form_end' => 'form',
        'csrf_token' => 'form',
        'logout_url' => 'security-http',
        'logout_path' => 'security-http',
        'is_granted' => 'security-core',
        'link' => 'web-link',
        'preload' => 'web-link',
        'dns_prefetch' => 'web-link',
        'preconnect' => 'web-link',
        'prefetch' => 'web-link',
        'prerender' => 'web-link',
        'workflow_can' => 'workflow',
        'workflow_transitions' => 'workflow',
        'workflow_has_marked_place' => 'workflow',
        'workflow_marked_places' => 'workflow',
    ];

    private static $fullStackEnable = [
        'form' => 'enable "framework.form"',
        'security-core' => 'add the "SecurityBundle"',
        'security-http' => 'add the "SecurityBundle"',
        'web-link' => 'enable "framework.web_link"',
        'workflow' => 'enable "framework.workflows"',
    ];

    public static function onUndefinedFilter($name)
    {
        if (!isset(self::$filterComponents[$name])) {
            return false;
        }

        self::onUndefined($name, 'filter', self::$filterComponents[$name]);
    }

    public static function onUndefinedFunction($name)
    {
        if (!isset(self::$functionComponents[$name])) {
            return false;
        }

        self::onUndefined($name, 'function', self::$functionComponents[$name]);
    }

    private static function onUndefined($name, $type, $component)
    {
        if (class_exists(FullStack::class) && isset(self::$fullStackEnable[$component])) {
            throw new SyntaxError(sprintf('Did you forget to %s? Unknown %s "%s".', self::$fullStackEnable[$component], $type, $name));
        }

        throw new SyntaxError(sprintf('Did you forget to run "composer require symfony/%s"? Unknown %s "%s".', $component, $type, $name));
    }
}
