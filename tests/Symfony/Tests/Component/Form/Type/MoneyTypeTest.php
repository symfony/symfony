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
    public function testPassMoneyPatternToRenderer()
    {
        \Locale::setDefault('de_DE');

        $form = $this->factory->create('money');
        $renderer = $this->factory->createRenderer($form, 'stub');

        $this->assertSame('{{ widget }} €', $renderer->getVar('money_pattern'));
    }
}