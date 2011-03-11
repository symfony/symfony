<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

use Symfony\Component\Form\HiddenField;

class HiddenFieldTest extends \PHPUnit_Framework_TestCase
{
    protected $field;

    protected function setUp()
    {
        $this->field = new HiddenField('name');
    }

    public function testIsHidden()
    {
        $this->assertTrue($this->field->isHidden());
    }
}