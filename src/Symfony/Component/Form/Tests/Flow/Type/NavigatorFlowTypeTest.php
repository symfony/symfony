<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Flow\Type;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Flow\Type\NavigatorFlowType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

class NavigatorFlowTypeTest extends TestCase
{
    private FormFactoryInterface $factory;

    protected function setUp(): void
    {
        $this->factory = Forms::createFormFactoryBuilder()->getFormFactory();
    }

    public function testDefaultOptionsDoNotIncludeReset()
    {
        $form = $this->factory->create(NavigatorFlowType::class);

        self::assertTrue($form->has('previous'));
        self::assertTrue($form->has('next'));
        self::assertTrue($form->has('finish'));
        self::assertFalse($form->has('reset'));
    }

    public function testWithResetOptionAddsResetButton()
    {
        $form = $this->factory->create(NavigatorFlowType::class, null, [
            'with_reset' => true,
        ]);

        self::assertTrue($form->has('previous'));
        self::assertTrue($form->has('next'));
        self::assertTrue($form->has('finish'));
        self::assertTrue($form->has('reset'));
    }
}
