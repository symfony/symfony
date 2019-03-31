<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Length;

/**
 * @author Renan Taranto <renantaranto@gmail.com>
 */
class LengthTest extends TestCase
{
    public function testNormalizerCanBeSet()
    {
        $length = new Length(['min' => 0, 'max' => 10, 'normalizer' => 'trim']);

        $this->assertEquals('trim', $length->normalizer);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\InvalidArgumentException
     * @expectedExceptionMessage The "normalizer" option must be a valid callable ("string" given).
     */
    public function testInvalidNormalizerThrowsException()
    {
        new Length(['min' => 0, 'max' => 10, 'normalizer' => 'Unknown Callable']);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\InvalidArgumentException
     * @expectedExceptionMessage The "normalizer" option must be a valid callable ("stdClass" given).
     */
    public function testInvalidNormalizerObjectThrowsException()
    {
        new Length(['min' => 0, 'max' => 10, 'normalizer' => new \stdClass()]);
    }
}
