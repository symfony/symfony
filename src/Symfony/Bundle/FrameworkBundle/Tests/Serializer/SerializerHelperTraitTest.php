<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Serializer;

use Symfony\Bundle\FrameworkBundle\Serializer\SerializerHelperTrait;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;

class SerializerHelperTraitTest extends TestCase
{
    public function testJsonWithoutSerializer()
    {
        $helper = new DummySerializerHelperWithContainer();
        $helper->setContainer(new Container());

        $response = $helper->json(array());
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('[]', $response->getContent());
    }

    public function testJsonWithSerializer()
    {
        $serializer = $this->getMock(SerializerInterface::class);
        $serializer
            ->expects($this->once())
            ->method('serialize')
            ->with(array(), 'json', array('json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS))
            ->will($this->returnValue('[]'));

        $helper = new DummySerializerHelper($serializer);

        $response = $helper->json(array());
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('[]', $response->getContent());
    }

    public function testJsonWithSerializerFromContainer()
    {
        $serializer = $this->getMock(SerializerInterface::class);
        $serializer
            ->expects($this->once())
            ->method('serialize')
            ->with(array(), 'json', array('json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS))
            ->will($this->returnValue('[]'));

        $container = new Container();
        $container->set('serializer', $serializer);

        $helper = new DummySerializerHelperWithContainer();
        $helper->setContainer($container);

        $response = $helper->json(array());
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('[]', $response->getContent());
    }

    public function testJsonWithSerializerContextOverride()
    {
        $serializer = $this->getMock(SerializerInterface::class);
        $serializer
            ->expects($this->once())
            ->method('serialize')
            ->with(array(), 'json', array('json_encode_options' => 0, 'other' => 'context'))
            ->will($this->returnValue('[]'));

        $helper = new DummySerializerHelper($serializer);

        $response = $helper->json(array(), 200, array(), array('json_encode_options' => 0, 'other' => 'context'));
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('[]', $response->getContent());
        $response->setEncodingOptions(JSON_FORCE_OBJECT);
        $this->assertEquals('{}', $response->getContent());
    }
}

class DummySerializerHelper
{
    use SerializerHelperTrait {
        json as public;
    }

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }
}

class DummySerializerHelperWithContainer implements ContainerAwareInterface
{
    use ContainerAwareTrait;
    use SerializerHelperTrait {
        json as public;
    }
}
