<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Kubernetes\Tests\Serializer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Bridge\Kubernetes\Serializer\CronJobNormalizer;
use Symfony\Component\Scheduler\Bridge\Kubernetes\Task\CronJob;
use Symfony\Component\Scheduler\Serializer\TaskNormalizer;
use Symfony\Component\Scheduler\Task\NullTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CronJobNormalizerTest extends TestCase
{
    public function testNormalizerCannotSupportNormalizationOnInvalidTask(): void
    {
        $taskNormalizer = new TaskNormalizer();
        $normalizer = new CronJobNormalizer($taskNormalizer);

        static::assertFalse($normalizer->supportsNormalization(new NullTask('foo'), 'json'));
    }

    public function testNormalizerCanSupportNormalizationOnValidTask(): void
    {
        $taskNormalizer = new TaskNormalizer();
        $normalizer = new CronJobNormalizer($taskNormalizer);

        static::assertTrue($normalizer->supportsNormalization(new CronJob('foo', '1.18'), 'json'));
    }
}
