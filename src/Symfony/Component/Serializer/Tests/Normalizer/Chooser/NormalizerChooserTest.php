<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer\Chooser;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\Chooser\NormalizerChooser;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Symfony\Component\Uid\Uuid;

class NormalizerChooserTest extends TestCase
{
    public function testChooseNormalizerWithNoNormalizer()
    {
        $chooser = new NormalizerChooser();
        $this->assertNull($chooser->chooseNormalizer([], 'foo'));
    }

    public function testChooseDenormalizerWithNoDenormalizer()
    {
        $chooser = new NormalizerChooser();
        $this->assertNull($chooser->chooseDenormalizer([], 'foo', \stdClass::class));
    }

    public function testChooseNormalizerWithPriority()
    {
        $chooser = new NormalizerChooser();

        $normalizers = [
            $dateTimeNormalizer = new DateTimeNormalizer(),
            $objectNormalizer = new ObjectNormalizer()
        ];

        $this->assertSame($dateTimeNormalizer, $chooser->chooseNormalizer($normalizers, new \DateTime()));
        $this->assertSame($objectNormalizer, $chooser->chooseNormalizer($normalizers, new \stdClass()));
    }

    public function testChooseDenormalizerWithPriority()
    {
        $chooser = new NormalizerChooser();

        $denormalizers = [
            $dateTimeNormalizer = new DateTimeNormalizer(),
            $uidNormalizer = new UidNormalizer()
        ];

        $this->assertSame($dateTimeNormalizer, $chooser->chooseNormalizer($denormalizers, new \DateTime()));
        $this->assertSame($uidNormalizer, $chooser->chooseNormalizer($denormalizers, Uuid::v4()));
    }
}
