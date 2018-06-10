<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Asynchronous\Serialization;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\Serialization\SerializerConfiguration;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class SerializerConfigurationTest extends TestCase
{
    public function testSerialiazable()
    {
        $config = new SerializerConfiguration(array(ObjectNormalizer::GROUPS => array('Default', 'Extra')));

        $this->assertTrue(is_subclass_of(SerializerConfiguration::class, \Serializable::class, true));
        $this->assertEquals($config, unserialize(serialize($config)));
    }
}
