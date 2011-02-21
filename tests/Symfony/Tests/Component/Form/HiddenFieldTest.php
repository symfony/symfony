<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

require_once __DIR__.'/TestCase.php';

use Symfony\Component\Form\HiddenField;

class HiddenFieldTest extends TestCase
{
    protected $field;

    protected function setUp()
    {
        parent::setUp();
        $this->field = $this->factory->getHiddenField('name');
    }

    public function testIsHidden()
    {
        $this->assertTrue($this->field->isHidden());
    }
}