<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\DeprecateJsonStreamerValueTransformerTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DeprecateJsonStreamerValueTransformerTagPassTest extends TestCase
{
    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testAddPropertyValueTransformerTagToLegacyValueTransformers()
    {
        $container = new ContainerBuilder();

        $container->register('json_streamer.stream_reader')->setArguments([null]);
        $container->register('json_streamer.stream_writer')->setArguments([null]);
        $container->register('.json_streamer.cache_warmer.streamer')->setArguments([null, null]);

        $container->register('deprecated_tag_service')
            ->addTag('json_streamer.value_transformer');

        $container->register('deprecated_and_new_tag_service')
            ->addTag('json_streamer.value_transformer')
            ->addTag('json_streamer.property_value_transformer');

        $this->expectUserDeprecationMessage('Since symfony/json-streamer 8.1: The "json_streamer.value_transformer" tag is deprecated, use "json_streamer.property_value_transformer" instead on service "deprecated_tag_service".');

        (new DeprecateJsonStreamerValueTransformerTagPass())->process($container);

        $tags = $container->getDefinition('deprecated_tag_service')->getTags();
        $this->assertArrayHasKey('json_streamer.property_value_transformer', $tags);
    }
}
