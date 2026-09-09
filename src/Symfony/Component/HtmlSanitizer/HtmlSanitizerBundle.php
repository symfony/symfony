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

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Provides the HTML sanitizer services.
 */
#[RequiredBundle(ServicesBundle::class)]
class HtmlSanitizerBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->canBeDisabled()
            ->children()
                ->arrayNode('sanitizers', 'sanitizer')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->enumNode('default_action')
                                ->info('Defines how the sanitizer must behave by default.')
                                ->values(['drop', 'block', 'allow'])
                            ->end()
                            ->booleanNode('allow_safe_elements')
                                ->info('Allows "safe" elements and attributes.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('allow_static_elements')
                                ->info('Allows all static elements and attributes from the W3C Sanitizer API standard.')
                                ->defaultFalse()
                            ->end()
                            ->arrayNode('allow_elements', 'allow_element')
                                ->info('Configures the elements that the sanitizer should retain from the input. The element name is the key, the value is either a list of allowed attributes for this element or "*" to allow the default set of attributes (https://wicg.github.io/sanitizer-api/#default-configuration).')
                                ->example(['i' => '*', 'a' => ['title'], 'span' => 'class'])
                                ->normalizeKeys(false)
                                ->useAttributeAsKey('name')
                                ->variablePrototype()
                                    ->beforeNormalization()
                                        ->ifArray()->then(static fn ($n) => $n['attribute'] ?? $n)
                                    ->end()
                                    ->validate()
                                        ->ifTrue(static fn ($n): bool => !\is_string($n) && !\is_array($n))
                                        ->thenInvalid('The value must be either a string or an array of strings.')
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('block_elements', 'block_element')
                                ->info('Configures elements as blocked. Blocked elements are elements the sanitizer should remove from the input, but retain their children.')
                                ->acceptAndWrap(['string'])
                                ->stringPrototype()->end()
                            ->end()
                            ->arrayNode('drop_elements', 'drop_element')
                                ->info('Configures elements as dropped. Dropped elements are elements the sanitizer should remove from the input, including their children.')
                                ->acceptAndWrap(['string'])
                                ->stringPrototype()->end()
                            ->end()
                            ->arrayNode('allow_attributes', 'allow_attribute')
                                ->info('Configures attributes as allowed. Allowed attributes are attributes the sanitizer should retain from the input.')
                                ->normalizeKeys(false)
                                ->useAttributeAsKey('name')
                                ->variablePrototype()
                                    ->beforeNormalization()
                                        ->ifArray()->then(static fn ($n) => $n['element'] ?? $n)
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('drop_attributes', 'drop_attribute')
                                ->info('Configures attributes as dropped. Dropped attributes are attributes the sanitizer should remove from the input.')
                                ->normalizeKeys(false)
                                ->useAttributeAsKey('name')
                                ->variablePrototype()
                                    ->beforeNormalization()
                                        ->ifArray()->then(static fn ($n) => $n['element'] ?? $n)
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('force_attributes', 'force_attribute')
                                ->info('Forcefully set the values of certain attributes on certain elements.')
                                ->normalizeKeys(false)
                                ->useAttributeAsKey('name')
                                ->arrayPrototype()
                                    ->normalizeKeys(false)
                                    ->useAttributeAsKey('name')
                                    ->stringPrototype()->end()
                                ->end()
                            ->end()
                            ->booleanNode('force_https_urls')
                                ->info('Transforms URLs using the HTTP scheme to use the HTTPS scheme instead.')
                                ->defaultFalse()
                            ->end()
                            ->arrayNode('allowed_link_schemes', 'allowed_link_scheme')
                                ->info('Allows only a given list of schemes to be used in links href attributes.')
                                ->acceptAndWrap(['string'])
                                ->stringPrototype()->end()
                            ->end()
                            ->arrayNode('allowed_link_hosts', 'allowed_link_host')
                                ->info('Allows only a given list of hosts to be used in links href attributes.')
                                ->defaultNull()
                                ->acceptAndWrap(['string'])
                                ->stringPrototype()->end()
                            ->end()
                            ->booleanNode('allow_relative_links')
                                ->info('Allows relative URLs to be used in links href attributes.')
                                ->defaultFalse()
                            ->end()
                            ->arrayNode('allowed_media_schemes', 'allowed_media_scheme')
                                ->info('Allows only a given list of schemes to be used in media source attributes (img, audio, video, ...).')
                                ->acceptAndWrap(['string'])
                                ->stringPrototype()->end()
                            ->end()
                            ->arrayNode('allowed_media_hosts', 'allowed_media_host')
                                ->info('Allows only a given list of hosts to be used in media source attributes (img, audio, video, ...).')
                                ->defaultNull()
                                ->acceptAndWrap(['string'])
                                ->stringPrototype()->end()
                            ->end()
                            ->booleanNode('allow_relative_medias')
                                ->info('Allows relative URLs to be used in media source attributes (img, audio, video, ...).')
                                ->defaultFalse()
                            ->end()
                            ->arrayNode('with_attribute_sanitizers', 'with_attribute_sanitizer')
                                ->info('Registers custom attribute sanitizers.')
                                ->acceptAndWrap(['string'])
                                ->stringPrototype()->end()
                            ->end()
                            ->arrayNode('without_attribute_sanitizers', 'without_attribute_sanitizer')
                                ->info('Unregisters custom attribute sanitizers.')
                                ->acceptAndWrap(['string'])
                                ->stringPrototype()->end()
                            ->end()
                            ->integerNode('max_input_length')
                                ->info('The maximum length allowed for the sanitized input.')
                                ->defaultValue(0)
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/html_sanitizer.php');

        foreach ($config['sanitizers'] as $sanitizerName => $sanitizerConfig) {
            $configId = 'html_sanitizer.config.'.$sanitizerName;
            $def = $container->register($configId, HtmlSanitizerConfig::class);

            // Base
            if ($sanitizerConfig['default_action'] ?? false) {
                $def->addMethodCall('defaultAction', [HtmlSanitizerAction::from($sanitizerConfig['default_action'])], true);
            }

            if ($sanitizerConfig['allow_safe_elements']) {
                $def->addMethodCall('allowSafeElements', [], true);
            }

            if ($sanitizerConfig['allow_static_elements']) {
                $def->addMethodCall('allowStaticElements', [], true);
            }

            // Configures elements
            foreach ($sanitizerConfig['allow_elements'] as $element => $attributes) {
                $def->addMethodCall('allowElement', [$element, $attributes], true);
            }

            foreach ($sanitizerConfig['block_elements'] as $element) {
                $def->addMethodCall('blockElement', [$element], true);
            }

            foreach ($sanitizerConfig['drop_elements'] as $element) {
                $def->addMethodCall('dropElement', [$element], true);
            }

            // Configures attributes
            foreach ($sanitizerConfig['allow_attributes'] as $attribute => $elements) {
                $def->addMethodCall('allowAttribute', [$attribute, $elements], true);
            }

            foreach ($sanitizerConfig['drop_attributes'] as $attribute => $elements) {
                $def->addMethodCall('dropAttribute', [$attribute, $elements], true);
            }

            // Force attributes
            foreach ($sanitizerConfig['force_attributes'] as $element => $attributes) {
                foreach ($attributes as $attrName => $attrValue) {
                    $def->addMethodCall('forceAttribute', [$element, $attrName, $attrValue], true);
                }
            }

            // Settings
            $def->addMethodCall('forceHttpsUrls', [$sanitizerConfig['force_https_urls']], true);
            if ($sanitizerConfig['allowed_link_schemes']) {
                $def->addMethodCall('allowLinkSchemes', [$sanitizerConfig['allowed_link_schemes']], true);
            }
            $def->addMethodCall('allowLinkHosts', [$sanitizerConfig['allowed_link_hosts']], true);
            $def->addMethodCall('allowRelativeLinks', [$sanitizerConfig['allow_relative_links']], true);
            if ($sanitizerConfig['allowed_media_schemes']) {
                $def->addMethodCall('allowMediaSchemes', [$sanitizerConfig['allowed_media_schemes']], true);
            }
            $def->addMethodCall('allowMediaHosts', [$sanitizerConfig['allowed_media_hosts']], true);
            $def->addMethodCall('allowRelativeMedias', [$sanitizerConfig['allow_relative_medias']], true);

            // Custom attribute sanitizers
            foreach ($sanitizerConfig['with_attribute_sanitizers'] as $serviceName) {
                $def->addMethodCall('withAttributeSanitizer', [new Reference($serviceName)], true);
            }

            foreach ($sanitizerConfig['without_attribute_sanitizers'] as $serviceName) {
                $def->addMethodCall('withoutAttributeSanitizer', [new Reference($serviceName)], true);
            }

            if ($sanitizerConfig['max_input_length']) {
                $def->addMethodCall('withMaxInputLength', [$sanitizerConfig['max_input_length']], true);
            }

            // Create the sanitizer and link its config
            $sanitizerId = 'html_sanitizer.sanitizer.'.$sanitizerName;
            $container->register($sanitizerId, HtmlSanitizer::class)
                ->addTag('html_sanitizer', ['sanitizer' => $sanitizerName])
                ->addArgument(new Reference($configId));

            if ('default' !== $sanitizerName) {
                $container->registerAliasForArgument($sanitizerId, HtmlSanitizerInterface::class, $sanitizerName);
            }
        }
    }
}
