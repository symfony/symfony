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

use Composer\InstalledVersions;
use Symfony\Bundle\FullStack;
use Twig\Error\SyntaxError;

/**
 * @internal
 */
class UndefinedCallableHandler
{
    private const FILTER_COMPONENTS = [
        'humanize' => 'form',
        'trans' => 'translation',
        'transchoice' => 'translation',
        'yaml_encode' => 'yaml',
        'yaml_dump' => 'yaml',
    ];

    private const FUNCTION_COMPONENTS = [
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

    private const FULL_STACK_ENABLE = [
        'form' => 'enable "framework.form"',
        'security-core' => 'add the "SecurityBundle"',
        'security-http' => 'add the "SecurityBundle"',
        'web-link' => 'enable "framework.web_link"',
        'workflow' => 'enable "framework.workflows"',
    ];

    public static function onUndefinedFilter(string $name): bool
    {
        if (!isset(self::FILTER_COMPONENTS[$name])) {
            return false;
        }

        self::onUndefined($name, 'filter', self::FILTER_COMPONENTS[$name]);

        return true;
    }

    public static function onUndefinedFunction(string $name): bool
    {
        if (!isset(self::FUNCTION_COMPONENTS[$name])) {
            return false;
        }

        self::onUndefined($name, 'function', self::FUNCTION_COMPONENTS[$name]);

        return true;
    }

    private static function onUndefined(string $name, string $type, string $component)
    {
        if (class_exists(FullStack::class) && isset(self::FULL_STACK_ENABLE[$component])) {
            throw new SyntaxError(sprintf('Did you forget to %s? Unknown %s "%s".', self::FULL_STACK_ENABLE[$component], $type, $name));
        }

        $missingPackage = 'symfony/'.$component;

        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled($missingPackage)) {
            $missingPackage = 'symfony/twig-bundle';
        }

        throw new SyntaxError(sprintf('Did you forget to run "composer require %s"? Unknown %s "%s".', $missingPackage, $type, $name));
    }
}
