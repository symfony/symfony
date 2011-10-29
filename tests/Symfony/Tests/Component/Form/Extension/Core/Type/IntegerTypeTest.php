<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\Type;

require_once __DIR__ . '/LocalizedTestCase.php';


class IntegerTypeTest extends LocalizedTestCase
{
    public function testSubmitCastsToInteger()
    {
        $form = $this->factory->create('integer');

        $form->bind('1.678');

        $this->assertSame(1, $form->getData());
        $this->assertSame('1', $form->getClientData());
    }
}
