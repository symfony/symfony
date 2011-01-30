<?php

namespace Symfony\Tests\Component\DependencyInjection\Configuration;

use Symfony\Component\DependencyInjection\Configuration\NodeInterface;
use Symfony\Component\DependencyInjection\Configuration\Builder\TreeBuilder;

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
                ->normalize('encoder')
                ->node('encoders', 'array')
                    ->key('class')
                    ->prototype('array')
                        ->before()->ifString()->then(function($v) { return array('algorithm' => $v); })->end()
                        ->node('algorithm', 'scalar')->end()
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
                ->node('logout', 'array')
                    ->normalize('handler')
                    ->node('handlers', 'array')
                        ->prototype('scalar')->end()
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