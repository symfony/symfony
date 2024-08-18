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

use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

class UrlTypeTest extends TextTypeTest
{
    use ExpectUserDeprecationMessageTrait;

    public const TESTED_TYPE = UrlType::class;

    /**
     * @group legacy
     */
    public function testSubmitAddsDefaultProtocolIfNoneIsIncluded()
    {
        $this->expectUserDeprecationMessage('Since symfony/form 7.1: Not configuring the "default_protocol" option when using the UrlType is deprecated. It will default to "null" in 8.0.');
        $form = $this->factory->create(static::TESTED_TYPE, 'name');

        $form->submit('www.domain.com');

        $this->assertSame('http://www.domain.com', $form->getData());
        $this->assertSame('http://www.domain.com', $form->getViewData());
    }

    public function testSubmitAddsNoDefaultProtocolIfAlreadyIncluded()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'default_protocol' => 'http',
        ]);

        $form->submit('ftp://www.domain.com');

        $this->assertSame('ftp://www.domain.com', $form->getData());
        $this->assertSame('ftp://www.domain.com', $form->getViewData());
    }

    public function testSubmitAddsNoDefaultProtocolIfEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'default_protocol' => 'http',
        ]);

        $form->submit('');

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData());
    }

    public function testSubmitAddsNoDefaultProtocolIfNull()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'default_protocol' => 'http',
        ]);

        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData());
    }

    public function testSubmitAddsNoDefaultProtocolIfSetToNull()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'default_protocol' => null,
        ]);

        $form->submit('www.domain.com');

        $this->assertSame('www.domain.com', $form->getData());
        $this->assertSame('www.domain.com', $form->getViewData());
    }

    public function testThrowExceptionIfDefaultProtocolIsInvalid()
    {
        $this->expectException(InvalidOptionsException::class);
        $this->factory->create(static::TESTED_TYPE, null, [
            'default_protocol' => [],
        ]);
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = 'empty', $expectedData = 'http://empty')
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'default_protocol' => 'http',
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        // listener normalizes data on submit
        $this->assertSame($expectedData, $form->getViewData());
        $this->assertSame($expectedData, $form->getNormData());
        $this->assertSame($expectedData, $form->getData());
    }

    protected function getTestOptions(): array
    {
        return ['default_protocol' => 'http'];
    }
}
