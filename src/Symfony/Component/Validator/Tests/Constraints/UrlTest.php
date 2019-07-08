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
use Symfony\Component\Validator\Constraints\Url;

/**
 * @author Renan Taranto <renantaranto@gmail.com>
 */
class UrlTest extends TestCase
{
    public function testNormalizerCanBeSet()
    {
        $url = new Url(['normalizer' => 'trim']);

        $this->assertEquals('trim', $url->normalizer);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\InvalidArgumentException
     * @expectedExceptionMessage The "normalizer" option must be a valid callable ("string" given).
     */
    public function testInvalidNormalizerThrowsException()
    {
        new Url(['normalizer' => 'Unknown Callable']);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\InvalidArgumentException
     * @expectedExceptionMessage The "normalizer" option must be a valid callable ("stdClass" given).
     */
    public function testInvalidNormalizerObjectThrowsException()
    {
        new Url(['normalizer' => new \stdClass()]);
    }
}
