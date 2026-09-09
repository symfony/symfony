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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

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

    /**
     * The build parameter through which a bundle names the package that provides each
     * configuration key it knows about, as a map of extension name to package name.
     */
    private const PACKAGES_PARAMETER = '.container.extension_packages';

    /**
     * Declares the package that provides each of the given configuration keys.
     *
     * @param array<string, string> $packages A map of extension name to package name
     */
    public static function addPackages(ContainerBuilder $container, array $packages): void
    {
        $container->setParameter(self::PACKAGES_PARAMETER, [...self::getPackages($container), ...$packages]);
    }

    /**
     * @return array<string, string>
     */
    public static function getPackages(ContainerBuilder $container): array
    {
        return $container->hasParameter(self::PACKAGES_PARAMETER) ? $container->getParameter(self::PACKAGES_PARAMETER) : [];
    }

    /**
     * Reports a configuration node whose value is forwarded to an extension nobody registered.
     */
    public static function createUndefinedAliasException(string $extensionName, string $key, string $alias, ContainerBuilder $container): LogicException
    {
        return new LogicException(\sprintf('The "%s.%s" configuration is handled by the "%s" extension, which is not registered.', $extensionName, $key, $alias).self::getPackageSuggestion($alias, self::getPackages($container)));
    }

    /**
     * Names the package to install for a configuration key a bundle has declared it knows about.
     *
     * @param array<string, string> $packages
     */
    public static function getPackageSuggestion(string $extensionName, array $packages): string
    {
        if (!isset($packages[$extensionName])) {
            return '';
        }

        return \sprintf(' Try running "composer require %s".', $packages[$extensionName]);
    }

    /**
     * @param array<string, string> $packages
     */
    public static function getErrorMessage(string $extensionName, ?string $loadingFilePath, string $namespaceOrAlias, array $foundExtensionNamespaces, array $packages = []): string
    {
        $message = '';
        if (isset(self::BUNDLE_EXTENSIONS[$extensionName])) {
            $message .= \sprintf('Did you forget to install or enable the %s? ', self::BUNDLE_EXTENSIONS[$extensionName]);
        }

        $message .= match (true) {
            \is_string($loadingFilePath) => \sprintf('There is no extension able to load the configuration for "%s" (in "%s"). ', $extensionName, $loadingFilePath),
            default => \sprintf('There is no extension able to load the configuration for "%s". ', $extensionName),
        };

        $message .= \sprintf('Looked for namespace "%s", found "%s".', $namespaceOrAlias, $foundExtensionNamespaces ? implode('", "', $foundExtensionNamespaces) : 'none');

        return $message.self::getPackageSuggestion($extensionName, $packages);
    }
}
