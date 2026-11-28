<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Dynamic;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Dynamic\DynamicField;

class DynamicFieldTest extends TestCase
{
    public function testFieldIsNotAddedByDefault()
    {
        $field = new DynamicField();

        $this->assertFalse($field->shouldBeAdded());
        $this->assertNull($field->getType());
        $this->assertSame([], $field->getOptions());
    }

    public function testAdd()
    {
        $field = new DynamicField();

        $this->assertSame($field, $field->add('text', ['required' => false]));
        $this->assertTrue($field->shouldBeAdded());
        $this->assertSame('text', $field->getType());
        $this->assertSame(['required' => false], $field->getOptions());
    }
}
