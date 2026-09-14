<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Exception;

use Symfony\Component\KeyManagement\Dsn;

/**
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
class UnsupportedSchemeException extends InvalidArgumentException
{
    /**
     * @param list<string> $supportedSchemes
     */
    public function __construct(Dsn $dsn, array $supportedSchemes = [])
    {
        $message = $supportedSchemes
            ? \sprintf('The "%s" scheme is not supported; supported schemes are: "%s".', $dsn->scheme, implode('", "', $supportedSchemes))
            : \sprintf('The "%s" scheme is not supported.', $dsn->scheme);

        parent::__construct($message);
    }
}
