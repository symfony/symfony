<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\SerializerExtension;
use Symfony\Bridge\Twig\Extension\SerializerRuntime;
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\SerializerModelFixture;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

/**
 * @author Jesse Rushlow <jr@rushlow.dev>
 */
class SerializerExtensionTest extends TestCase
{
    /**
     * @dataProvider serializerDataProvider
     */
    public function testSerializeFilter(string $template, string $expectedResult)
    {
        $twig = $this->getTwig($template);

        self::assertSame($expectedResult, $twig->render('template', ['object' => new SerializerModelFixture()]));
    }

    public static function serializerDataProvider(): \Generator
    {
        yield ['{{ object|serialize }}', '{&quot;name&quot;:&quot;howdy&quot;,&quot;title&quot;:&quot;fixture&quot;}'];
        yield ['{{ object|serialize(\'yaml\') }}', '{ name: howdy, title: fixture }'];
        yield ['{{ object|serialize(\'yaml\', {groups: \'read\'}) }}', '{ name: howdy }'];
    }

    private function getTwig(string $template): Environment
    {
        $meta = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $runtime = new SerializerRuntime(new Serializer([new ObjectNormalizer($meta)], [new JsonEncoder(), new YamlEncoder()]));

        $mockRuntimeLoader = $this->createMock(RuntimeLoaderInterface::class);
        $mockRuntimeLoader
            ->method('load')
            ->willReturnMap([
                ['Symfony\Bridge\Twig\Extension\SerializerRuntime', $runtime],
            ])
        ;

        $twig = new Environment(new ArrayLoader(['template' => $template]));
        $twig->addExtension(new SerializerExtension());
        $twig->addRuntimeLoader($mockRuntimeLoader);

        return $twig;
    }
}
