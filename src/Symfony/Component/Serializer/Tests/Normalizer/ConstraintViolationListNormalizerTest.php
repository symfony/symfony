<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ConstraintViolationListNormalizerTest extends TestCase
{
    private $normalizer;

    protected function setUp()
    {
        $this->normalizer = new ConstraintViolationListNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new ConstraintViolationList()));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testNormalize()
    {
        $list = new ConstraintViolationList(array(
            new ConstraintViolation('a', 'b', array(), 'c', 'd', 'e', null, 'f'),
            new ConstraintViolation('1', '2', array(), '3', '4', '5', null, '6'),
        ));

        $expected = array(
            'type' => 'https://symfony.com/errors/validation',
            'title' => 'Validation Failed',
            'detail' => 'd: a
4: 1',
            'violations' => array(
                    array(
                        'propertyPath' => 'd',
                        'title' => 'a',
                        'type' => 'urn:uuid:f',
                    ),
                    array(
                        'propertyPath' => '4',
                        'title' => '1',
                        'type' => 'urn:uuid:6',
                    ),
                ),
        );

        $this->assertEquals($expected, $this->normalizer->normalize($list));
    }
}
