<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Tests\Component\Config\Definition;

use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class NormalizerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getEncoderTests
     */
    public function testNormalizeEncoders($denormalized)
    {
        $tb = new TreeBuilder();
        $tree = $tb
            ->root('root_name', 'array')
                ->fixXmlConfig('encoder')
                ->children()
                    ->node('encoders', 'array')
                        ->useAttributeAsKey('class')
                        ->prototype('array')
                            ->beforeNormalization()->ifString()->then(function($v) { return array('algorithm' => $v); })->end()
                            ->children()
                                ->node('algorithm', 'scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->buildTree()
        ;

        $normalized = array(
            'encoders' => array(
                'foo' => array('algorithm' => 'plaintext'),
            ),
        );

        $this->assertNormalized($tree, $denormalized, $normalized);
    }

    public function getEncoderTests()
    {
        $configs = array();

        // XML
        $configs[] = array(
            'encoder' => array(
                array('class' => 'foo', 'algorithm' => 'plaintext'),
            ),
        );

        // XML when only one element of this type
        $configs[] = array(
            'encoder' => array('class' => 'foo', 'algorithm' => 'plaintext'),
        );

        // YAML/PHP
        $configs[] = array(
            'encoders' => array(
                array('class' => 'foo', 'algorithm' => 'plaintext'),
            ),
        );

        // YAML/PHP
        $configs[] = array(
            'encoders' => array(
                'foo' => 'plaintext',
            ),
        );

        // YAML/PHP
        $configs[] = array(
            'encoders' => array(
                'foo' => array('algorithm' => 'plaintext'),
            ),
        );

        return array_map(function($v) {
            return array($v);
        }, $configs);
    }

    /**
     * @dataProvider getAnonymousKeysTests
     */
    public function testAnonymousKeysArray($denormalized)
    {
        $tb = new TreeBuilder();
        $tree = $tb
            ->root('root', 'array')
                ->children()
                    ->node('logout', 'array')
                        ->fixXmlConfig('handler')
                        ->children()
                            ->node('handlers', 'array')
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->buildTree()
        ;

        $normalized = array('logout' => array('handlers' => array('a', 'b', 'c')));

        $this->assertNormalized($tree, $denormalized, $normalized);
    }

    public function getAnonymousKeysTests()
    {
        $configs = array();

        $configs[] = array(
            'logout' => array(
                'handlers' => array('a', 'b', 'c'),
            ),
        );

        $configs[] = array(
            'logout' => array(
                'handler' => array('a', 'b', 'c'),
            ),
        );

        return array_map(function($v) { return array($v); }, $configs);
    }

    public static function assertNormalized(NodeInterface $tree, $denormalized, $normalized)
    {
        self::assertSame($normalized, $tree->normalize($denormalized));
    }
}
