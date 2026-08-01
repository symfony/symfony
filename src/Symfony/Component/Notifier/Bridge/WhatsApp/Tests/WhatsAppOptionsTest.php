<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\WhatsApp\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\WhatsApp\WhatsAppOptions;

/**
 * @author Piero Recchia <piero.recchia@gmail.com>
 */
final class WhatsAppOptionsTest extends TestCase
{
    public function testGetRecipientIdReturnsTheConfiguredPhoneNumber()
    {
        $options = (new WhatsAppOptions())->recipientPhoneNumber('5491112345678');

        self::assertSame('5491112345678', $options->getRecipientId());
    }

    public function testGetRecipientIdIsNullByDefault()
    {
        self::assertNull((new WhatsAppOptions())->getRecipientId());
    }

    public function testGetTemplateIsNullByDefault()
    {
        self::assertNull((new WhatsAppOptions())->getTemplate());
    }

    public function testGetTemplateReturnsTheConfiguredTemplate()
    {
        $options = (new WhatsAppOptions())->template('recordatorio_turno', 'es_AR', ['Ana']);

        self::assertSame([
            'name' => 'recordatorio_turno',
            'languageCode' => 'es_AR',
            'bodyParameters' => ['Ana'],
        ], $options->getTemplate());
    }

    public function testToArrayWithoutATemplate()
    {
        $options = (new WhatsAppOptions())->recipientPhoneNumber('5491112345678');

        self::assertSame([
            'recipientPhoneNumber' => '5491112345678',
            'template' => null,
        ], $options->toArray());
    }

    public function testToArrayWithATemplate()
    {
        $options = (new WhatsAppOptions())
            ->recipientPhoneNumber('5491112345678')
            ->template('recordatorio_turno', 'es_AR', ['Ana', 'Corte de pelo']);

        self::assertSame([
            'recipientPhoneNumber' => '5491112345678',
            'template' => [
                'name' => 'recordatorio_turno',
                'languageCode' => 'es_AR',
                'bodyParameters' => ['Ana', 'Corte de pelo'],
            ],
        ], $options->toArray());
    }
}
