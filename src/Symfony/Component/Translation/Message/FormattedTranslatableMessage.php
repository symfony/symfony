<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Message;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Jakub Caban <kuba.iluvatar@gmail.com>
 */
class FormattedTranslatableMessage implements TranslatableInterface
{
    use TranslatableParametersTrait;

    private $format;

    public function __construct(
        string $format,
        ...$parameters
    ) {
        $this->format = $format;
        $this->parameters = $parameters;
    }

    public function __toString(): string
    {
        return $this->getFormat();
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function trans(TranslatorInterface $translator, string $locale = null): string
    {
        return sprintf(
            $this->getFormat(),
            ...$this->getTranslatedParameters($translator, $locale)
        );
    }
}
