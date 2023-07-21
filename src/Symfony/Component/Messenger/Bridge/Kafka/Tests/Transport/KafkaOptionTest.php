<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Kafka\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Kafka\Transport\KafkaOption;

/**
 * @requires extension rdkafka
 */
class KafkaOptionTest extends TestCase
{
    public function testProducer()
    {
        self::assertIsArray(KafkaOption::producer());

        foreach (KafkaOption::producer() as $option) {
            self::assertTrue(\in_array($option, ['P', '*']));
        }
    }

    public function testConsumer()
    {
        self::assertIsArray(KafkaOption::consumer());

        foreach (KafkaOption::consumer() as $option) {
            self::assertTrue(\in_array($option, ['C', '*']));
        }
    }

    public function testGlobal()
    {
        self::assertIsArray(KafkaOption::global());

        foreach (KafkaOption::global() as $option) {
            self::assertEquals('*', $option);
        }
    }
}
