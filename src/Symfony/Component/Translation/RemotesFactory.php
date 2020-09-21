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

use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Remote\Dsn;
use Symfony\Component\Translation\Remote\RemoteDecorator;
use Symfony\Component\Translation\Remote\RemoteFactoryInterface;
use Symfony\Component\Translation\Remote\RemoteInterface;

class RemotesFactory
{
    private $factories;
    private $enabledLocales;

    /**
     * @param RemoteFactoryInterface[] $factories
     */
    public function __construct(iterable $factories, array $enabledLocales)
    {
        $this->factories = $factories;
        $this->enabledLocales = $enabledLocales;
    }

    public function fromConfig(array $config): Remotes
    {
        $remotes = [];
        foreach ($config as $name => $currentConfig) {
            $remotes[$name] = $this->fromString(
                $currentConfig['dsn'],
                !$currentConfig['locales'] ? $this->enabledLocales : $currentConfig['locales'],
                !$currentConfig['domains'] ? [] : $currentConfig['domains']
            );
        }

        return new Remotes($remotes);
    }

    public function fromString(string $dsn, array $locales, array $domains = []): RemoteInterface
    {
        return $this->fromDsnObject(Dsn::fromString($dsn), $locales, $domains);
    }

    public function fromDsnObject(Dsn $dsn, array $locales, array $domains = []): RemoteInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return new RemoteDecorator($factory->create($dsn), $locales, $domains);
            }
        }

        throw new UnsupportedSchemeException($dsn);
    }
}
