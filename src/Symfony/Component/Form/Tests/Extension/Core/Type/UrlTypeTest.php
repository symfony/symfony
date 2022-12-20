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

use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

class UrlTypeTest extends TextTypeTest
{
    public const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\UrlType';

    public function testSubmitAddsDefaultProtocolIfNoneIsIncluded()
    {
        $form = $this->factory->create(static::TESTED_TYPE, 'name');

        $form->submit('www.domain.com');

        self::assertSame('http://www.domain.com', $form->getData());
        self::assertSame('http://www.domain.com', $form->getViewData());
    }

    public function testSubmitAddsNoDefaultProtocolIfAlreadyIncluded()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'default_protocol' => 'http',
        ]);

        $form->submit('ftp://www.domain.com');

        self::assertSame('ftp://www.domain.com', $form->getData());
        self::assertSame('ftp://www.domain.com', $form->getViewData());
    }

    public function testSubmitAddsNoDefaultProtocolIfEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'default_protocol' => 'http',
        ]);

        $form->submit('');

        self::assertNull($form->getData());
        self::assertSame('', $form->getViewData());
    }

    public function testSubmitAddsNoDefaultProtocolIfNull()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'default_protocol' => 'http',
        ]);

        $form->submit(null);

        self::assertNull($form->getData());
        self::assertSame('', $form->getViewData());
    }

    public function testSubmitAddsNoDefaultProtocolIfSetToNull()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'default_protocol' => null,
        ]);

        $form->submit('www.domain.com');

        self::assertSame('www.domain.com', $form->getData());
        self::assertSame('www.domain.com', $form->getViewData());
    }

    public function testThrowExceptionIfDefaultProtocolIsInvalid()
    {
        self::expectException(InvalidOptionsException::class);
        $this->factory->create(static::TESTED_TYPE, null, [
            'default_protocol' => [],
        ]);
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = 'empty', $expectedData = 'http://empty')
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        // listener normalizes data on submit
        self::assertSame($expectedData, $form->getViewData());
        self::assertSame($expectedData, $form->getNormData());
        self::assertSame($expectedData, $form->getData());
    }
}
