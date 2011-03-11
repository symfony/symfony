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

require_once __DIR__ . '/LocalizedTestCase.php';

use Symfony\Component\Form\IntegerField;

class IntegerFieldTest extends LocalizedTestCase
{
    public function testSubmitCastsToInteger()
    {
        $field = new IntegerField('name');

        $field->submit('1.678');

        $this->assertSame(1, $field->getData());
        $this->assertSame('1', $field->getDisplayedData());
    }
}