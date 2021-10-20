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
class ImplodedTranslatableMessage implements TranslatableInterface
{
    use TranslatableParametersTrait;

    private $glue;

    public function __construct(
        string $glue = '',
        ...$parameters
    ) {
        $this->glue = $glue;
        $this->parameters = $parameters;
    }

    public function getGlue(): string
    {
        return $this->glue;
    }

    public function trans(TranslatorInterface $translator, string $locale = null): string
    {
        return implode(
            $this->getGlue(),
            $this->getTranslatedParameters($translator, $locale)
        );
    }
}
