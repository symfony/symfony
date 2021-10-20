<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

use Symfony\Component\Translation\Message\FormattedTranslatableMessage;
use Symfony\Component\Translation\Message\ImplodedTranslatableMessage;
use Symfony\Component\Translation\Message\NonTranslatableMessage;

if (!\function_exists('Symfony\Component\Translation\t')) {
    /**
     * @author Nate Wiebe <nate@northern.co>
     */
    function t(string $message, array $parameters = [], string $domain = null): TranslatableMessage
    {
        return new TranslatableMessage($message, $parameters, $domain);
    }
}

if (!\function_exists('Symfony\Component\Translation\ft')) {
    /**
     * @author Jakub Caban <kuba.iluvatar@gmail.com>
     */
    function ft(string $format, ...$parameters): FormattedTranslatableMessage
    {
        return new FormattedTranslatableMessage($format, $parameters);
    }
}

if (!\function_exists('Symfony\Component\Translation\it')) {
    /**
     * @author Jakub Caban <kuba.iluvatar@gmail.com>
     */
    function it(string $glue, ...$parameters): ImplodedTranslatableMessage
    {
        return new ImplodedTranslatableMessage($glue, $parameters);
    }
}

if (!\function_exists('Symfony\Component\Translation\nt')) {
    /**
     * @author Jakub Caban <kuba.iluvatar@gmail.com>
     */
    function nt(string $message): NonTranslatableMessage
    {
        return new NonTranslatableMessage($message);
    }
}
