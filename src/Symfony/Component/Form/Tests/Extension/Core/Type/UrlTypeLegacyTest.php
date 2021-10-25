<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

/**
 * @group legacy
 */
class UrlTypeLegacyTest extends UrlTypeTest
{
    /**
     * Legacy behavior. Replace test in parent class.
     */
    public function testSubmitAddsNoDefaultProtocolToEmail()
    {
        $form = $this->factory->create(static::TESTED_TYPE, 'name', $this->getTestOptions());

        $form->submit('contact@domain.com');

        $this->assertSame('http://contact@domain.com', $form->getData());
        $this->assertSame('http://contact@domain.com', $form->getViewData());
    }

    protected function getTestOptions(): array
    {
        return [];
    }
}
