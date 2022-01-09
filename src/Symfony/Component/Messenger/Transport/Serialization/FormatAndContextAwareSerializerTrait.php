<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Serialization;

/**
 * @author Nicolas PHILIPPE <nikophil@gmail.com>
 */
trait FormatAndContextAwareSerializerTrait
{
    private string $format;
    private array $context;

    public function setFormat(string $format): void
    {
        $this->format = $format;
    }

    public function setContext(array $context): void
    {
        $this->context = $context + [Serializer::MESSENGER_SERIALIZATION_CONTEXT => true];
    }
}
