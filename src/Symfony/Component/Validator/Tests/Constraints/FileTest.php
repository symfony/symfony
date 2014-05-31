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

use Symfony\Component\Validator\Constraints\File;

class FileTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @param mixed $maxSize
     * @param mixed $bytes
     * @dataProvider provideValidSizes
     */
    public function testMaxSize($maxSize, $bytes)
    {
        $file = new File(array('maxSize' => $maxSize));

        $this->assertSame($bytes, $file->maxSize);
    }

    /**
     * @param mixed $maxSize
     * @param mixed $bytes
     * @dataProvider provideInValidSizes
     * @expectedException Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalideMaxSize($maxSize)
    {
        $file = new File(array('maxSize' => $maxSize));
    }

    /**
     * @return array
     */
    public function provideValidSizes()
    {
        return array(
            array('500', 500),
            array(12300, 12300),
            array('1ki', 1024),
            array('1KI', 1024),
            array('2k', 2000),
            array('2K', 2000),
            array('1mi', 1048576),
            array('1MI', 1048576),
            array('3m', 3000000),
            array('3M', 3000000),
        );
    }

    /**
     * @return array
     */
    public function provideInvalidSizes()
    {
        return array(
            array('+100'),
            array('foo'),
            array('1Ko'),
            array('1kio'),
            array('1G'),
            array('1Gi'),
        );
    }
}
