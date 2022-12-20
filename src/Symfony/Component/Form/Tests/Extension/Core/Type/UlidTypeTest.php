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

use Symfony\Component\Form\Extension\Core\Type\UlidType;
use Symfony\Component\Uid\Ulid;

final class UlidTypeTest extends BaseTypeTest
{
    public const TESTED_TYPE = UlidType::class;

    public function testPassUlidToView()
    {
        $ulid = '01D85PP1982GF6KTVFHQ7W78FB';

        $form = $this->factory->create(static::TESTED_TYPE);
        $form->setData(new Ulid($ulid));

        self::assertSame($ulid, $form->createView()->vars['value']);
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = '', $expectedData = null)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        self::assertSame($expectedData, $form->getViewData());
        self::assertSame($expectedData, $form->getNormData());
        self::assertSame($expectedData, $form->getData());
    }
}
