<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader;

class UndefinedExtensionHandler
{
    private const BUNDLE_EXTENSIONS = [
        'debug' => 'DebugBundle',
        'doctrine' => 'DoctrineBundle',
        'doctrine_migrations' => 'DoctrineMigrationsBundle',
        'framework' => 'FrameworkBundle',
        'maker' => 'MakerBundle',
        'monolog' => 'MonologBundle',
        'security' => 'SecurityBundle',
        'twig' => 'TwigBundle',
        'twig_component' => 'TwigComponentBundle',
        'ux_icons' => 'UXIconsBundle',
        'web_profiler' => 'WebProfilerBundle',
    ];

    public static function getErrorMessage(string $extensionName, ?string $loadingFilePath, string $namespaceOrAlias, array $foundExtensionNamespaces): string
    {
        $message = '';
        if (isset(self::BUNDLE_EXTENSIONS[$extensionName])) {
            $message .= sprintf('Did you forget to install or enable the %s? ', self::BUNDLE_EXTENSIONS[$extensionName]);
        }

        $message .= match (true) {
            \is_string($loadingFilePath) => sprintf('There is no extension able to load the configuration for "%s" (in "%s"). ', $extensionName, $loadingFilePath),
            default => sprintf('There is no extension able to load the configuration for "%s". ', $extensionName),
        };

        $message .= sprintf('Looked for namespace "%s", found "%s".', $namespaceOrAlias, $foundExtensionNamespaces ? implode('", "', $foundExtensionNamespaces) : 'none');

        return $message;
    }
}
