<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Find all service tags which are defined, but not used and yield a warning log message.
 *
 * @author Florian Pfitzer <pfitzer@wurzel3.de>
 */
class UnusedTagsPass implements CompilerPassInterface
{
    private $knownTags = [
        'annotations.cached_reader',
        'auto_alias',
        'cache.pool',
        'cache.pool.clearer',
        'config_cache.resource_checker',
        'console.command',
        'container.env_var_processor',
        'container.hot_path',
        'container.service_locator',
        'container.service_subscriber',
        'controller.argument_value_resolver',
        'controller.service_arguments',
        'data_collector',
        'form.type',
        'form.type_extension',
        'form.type_guesser',
        'kernel.cache_clearer',
        'kernel.cache_warmer',
        'kernel.event_listener',
        'kernel.event_subscriber',
        'kernel.fragment_renderer',
        'kernel.reset',
        'monolog.logger',
        'property_info.access_extractor',
        'property_info.list_extractor',
        'property_info.type_extractor',
        'proxy',
        'routing.expression_language_provider',
        'routing.loader',
        'security.expression_language_provider',
        'security.remember_me_aware',
        'security.voter',
        'serializer.encoder',
        'serializer.normalizer',
        'templating.helper',
        'translation.dumper',
        'translation.extractor',
        'translation.loader',
        'twig.extension',
        'twig.loader',
        'twig.runtime',
        'validator.constraint_validator',
        'validator.initializer',
        'workflow.definition',
    ];

    public function process(ContainerBuilder $container)
    {
        $tags = array_unique(array_merge($container->findTags(), $this->knownTags));

        foreach ($container->findUnusedTags() as $tag) {
            // skip known tags
            if (\in_array($tag, $this->knownTags)) {
                continue;
            }

            // check for typos
            $candidates = [];
            foreach ($tags as $definedTag) {
                if ($definedTag === $tag) {
                    continue;
                }

                if (false !== strpos($definedTag, $tag) || levenshtein($tag, $definedTag) <= \strlen($tag) / 3) {
                    $candidates[] = $definedTag;
                }
            }

            $services = array_keys($container->findTaggedServiceIds($tag));
            $message = sprintf('Tag "%s" was defined on service(s) "%s", but was never used.', $tag, implode('", "', $services));
            if (!empty($candidates)) {
                $message .= sprintf(' Did you mean "%s"?', implode('", "', $candidates));
            }

            $container->log($this, $message);
        }
    }
}
