<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Flysystem;

use Psr\Container\ContainerInterface;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;
use Symfony\Component\KeyManagement\Factory\KmsFactoryInterface;
use Symfony\Component\KeyManagement\Local\OpenSslKms;
use Symfony\Component\KeyManagement\Local\SealedBoxKms;
use Symfony\Component\KeyManagement\Local\SodiumKms;

/**
 * Builds one of the local KMS backends (`SodiumKms`, `OpenSslKms`,
 * `SealedBoxKms`) wrapped around a {@see FlysystemKeyLoader}.
 *
 * Three schemes are supported, one per backend:
 *
 *   - `sodium+fly://<flysystem-service-id>/<path>?ext=.bin`
 *   - `openssl+fly://<flysystem-service-id>/<path>?ext=.bin`
 *   - `sodium-sealed-box+fly://<flysystem-service-id>/<path>?ext=.bin`
 *
 * The host segment is the id of a `League\Flysystem\FilesystemReader` service
 * registered with the application (typically through `league/flysystem-bundle`,
 * whose storages {@see DependencyInjection\RegisterFlysystemStoragesPass} makes
 * reachable under the name they were declared with). A Flysystem service
 * registered by hand is declared by tagging it `key_management.flysystem`, with
 * a `key` attribute matching the host.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class FlysystemKmsFactory implements KmsFactoryInterface
{
    private const array SCHEMES = ['sodium+fly', 'openssl+fly', 'sodium-sealed-box+fly'];

    public function __construct(
        private readonly ContainerInterface $flysystems,
    ) {
    }

    public function supports(Dsn $dsn): bool
    {
        return \in_array($dsn->scheme, self::SCHEMES, true);
    }

    public function create(Dsn $dsn): EncrypterInterface&DecrypterInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn, self::SCHEMES);
        }

        self::validateOptions($dsn, ['ext']);

        $loader = $this->buildKeyLoader($dsn);

        return match ($dsn->scheme) {
            'sodium+fly' => new SodiumKms($loader),
            'openssl+fly' => new OpenSslKms($loader),
            'sodium-sealed-box+fly' => new SealedBoxKms($loader),
            default => throw new UnsupportedSchemeException($dsn, self::SCHEMES),
        };
    }

    private function buildKeyLoader(Dsn $dsn): FlysystemKeyLoader
    {
        $serviceId = $dsn->host;
        if (null === $serviceId || '' === $serviceId) {
            throw new InvalidArgumentException(\sprintf('The "%s://" DSN must include the Flysystem service id as host (e.g. "%s://app.flysystem.s3/keys").', $dsn->scheme, $dsn->scheme));
        }
        if (!$this->flysystems->has($serviceId)) {
            throw new InvalidArgumentException(\sprintf('Flysystem service "%s" is not registered.', $serviceId));
        }

        return new FlysystemKeyLoader(
            $this->flysystems->get($serviceId),
            ltrim($dsn->path, '/'),
            (string) $dsn->getOption('ext', ''),
        );
    }

    /**
     * @param list<string> $supported
     */
    private static function validateOptions(Dsn $dsn, array $supported): void
    {
        foreach ($dsn->options as $option => $value) {
            if (!\in_array($option, $supported, true)) {
                throw new InvalidArgumentException(\sprintf('Unknown option "%s" in the "%s://" DSN; supported options are "%s".', $option, $dsn->scheme, implode('", "', $supported)));
            }
            if (!\is_scalar($value)) {
                throw new InvalidArgumentException(\sprintf('The "%s" option of the "%s://" DSN must be a scalar value.', $option, $dsn->scheme));
            }
        }
    }
}
