<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Type;

require_once __DIR__ . '/LocalizedTestCase.php';

class MoneyTypeTest extends LocalizedTestCase
{
    public function testPassMoneyPatternToContext()
    {
        \Locale::setDefault('de_DE');

        $form = $this->factory->create('money');
        $context = $form->getContext();

        $this->assertSame('{{ widget }} €', $context->getVar('money_pattern'));
    }
}