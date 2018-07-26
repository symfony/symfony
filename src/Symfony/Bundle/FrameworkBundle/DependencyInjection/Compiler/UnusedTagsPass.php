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
    private $whitelist = array(
        'annotations.cached_reader',
        'cache.pool.clearer',
        'console.command',
        'container.hot_path',
        'container.service_locator',
        'container.service_subscriber',
        'controller.service_arguments',
        'config_cache.resource_checker',
        'data_collector',
        'form.type',
        'form.type_extension',
        'form.type_guesser',
        'kernel.cache_clearer',
        'kernel.cache_warmer',
        'kernel.event_listener',
        'kernel.event_subscriber',
        'kernel.fragment_renderer',
        'monolog.logger',
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
        'validator.constraint_validator',
        'validator.initializer',
    );

    public function process(ContainerBuilder $container)
    {
        $tags = array_unique(array_merge($container->findTags(), $this->whitelist));

        foreach ($container->findUnusedTags() as $tag) {
            // skip whitelisted tags
            if (\in_array($tag, $this->whitelist)) {
                continue;
            }

            // check for typos
            $candidates = array();
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
