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
use Symfony\Component\Validator\Constraints\Schema;

class SchemaTest extends TestCase
{
    public function testEmptyFieldsInOptions()
    {
        $constraint = new Schema(
            format: 'YAML',
            invalidMessage: 'fooo',
        );

        $this->assertSame([], $constraint->constraints);
        $this->assertSame('YAML', $constraint->format);
        $this->assertSame('fooo', $constraint->invalidMessage);
        $this->assertSame(0, $constraint->flags);
    }

    public function testUpperFormat()
    {
        $constraint = new Schema(
            format: 'yaml',
        );

        $this->assertSame('YAML', $constraint->format);
    }
}
