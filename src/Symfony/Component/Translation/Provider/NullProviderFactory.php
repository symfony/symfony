<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Provider;

use Symfony\Component\Translation\Exception\UnsupportedSchemeException;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * @experimental in 5.3
 */
final class NullProviderFactory extends AbstractProviderFactory
{
    const SCHEME = 'null';

    public function create(Dsn $dsn): ProviderInterface
    {
        if (self::SCHEME === $dsn->getScheme()) {
            return new NullProvider();
        }

        throw new UnsupportedSchemeException($dsn, self::SCHEME, $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return [self::SCHEME];
    }
}
