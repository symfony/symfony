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
trait TranslatableParametersTrait
{
    private $parameters = [];

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getTranslatedParameters(
        TranslatorInterface $translator,
        ?string $locale
    ): array
    {
        return array_map(
            static function ($parameter) use ($translator, $locale) {
                return $parameter instanceof TranslatableInterface ? $parameter->trans($translator, $locale) : $parameter;
            },
            $this->getParameters()
        );
    }
}
