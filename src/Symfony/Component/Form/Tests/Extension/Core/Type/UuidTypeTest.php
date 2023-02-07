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

use Symfony\Component\Form\Extension\Core\Type\UuidType;
use Symfony\Component\Uid\Uuid;

final class UuidTypeTest extends BaseTypeTestCase
{
    public const TESTED_TYPE = UuidType::class;

    public function testPassUuidToView()
    {
        $uuid = '123e4567-e89b-12d3-a456-426655440000';

        $form = $this->factory->create(static::TESTED_TYPE);
        $form->setData(new Uuid($uuid));

        $this->assertSame($uuid, $form->createView()->vars['value']);
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = '', $expectedData = null)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        $this->assertSame($expectedData, $form->getViewData());
        $this->assertSame($expectedData, $form->getNormData());
        $this->assertSame($expectedData, $form->getData());
    }
}
