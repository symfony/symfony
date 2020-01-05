<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Tests;

use Doctrine\Common\Annotations\AnnotationReader;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AutoMapper\AutoMapper;
use Symfony\Component\AutoMapper\Generator\Generator;
use Symfony\Component\AutoMapper\Loader\ClassLoaderInterface;
use Symfony\Component\AutoMapper\Loader\FileLoader;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;

/**
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
abstract class AutoMapperBaseTest extends TestCase
{
    /** @var AutoMapper */
    protected $autoMapper;

    /** @var ClassLoaderInterface */
    protected $loader;

    public function setUp(): void
    {
        @unlink(__DIR__.'/cache/registry.php');
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $this->loader = new FileLoader(new Generator(
            (new ParserFactory())->create(ParserFactory::PREFER_PHP7),
            new ClassDiscriminatorFromClassMetadata($classMetadataFactory)
        ), __DIR__.'/cache');

        $this->autoMapper = AutoMapper::create(true, $this->loader);
    }
}
