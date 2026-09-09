<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\AzureKeyVault;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;
use Symfony\Component\KeyManagement\Factory\KmsFactoryInterface;

/**
 * Builds an {@see AzureKeyVault} from a DSN of the form:
 *
 *     azure-keyvault://<clientId>:<clientSecret>@<vault-name>.vault.azure.net?tenant=<tenantId>[&algorithm=...&wrap_algorithm=...&api_version=...&audience=...]
 *
 * The host is the full vault DNS name (`<name>.vault.azure.net`,
 * `<name>.managedhsm.azure.net` for Managed HSM, or the equivalent in a
 * sovereign cloud). The audience for token acquisition is inferred from the
 * host suffix and falls back to the standard `https://vault.azure.net/.default`
 * scope; pass `audience` explicitly to target a sovereign cloud (US gov,
 * China, ...) or to override the heuristic.
 *
 * Users that need Managed Identity, Workload Identity, or any other Azure AD
 * flow should wire {@see AzureKeyVault} manually with a custom
 * {@see TokenProviderInterface}.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class AzureKeyVaultFactory implements KmsFactoryInterface
{
    private const string SCHEME = 'azure-keyvault';
    private const string DEFAULT_AUDIENCE = 'https://vault.azure.net/.default';
    private const string MANAGED_HSM_AUDIENCE = 'https://managedhsm.azure.net/.default';
    private const array MANAGED_HSM_HOST_SUFFIXES = [
        '.managedhsm.azure.net',
        '.managedhsm.usgovcloudapi.net',
        '.managedhsm.azure.cn',
    ];

    public function supports(Dsn $dsn): bool
    {
        return self::SCHEME === $dsn->scheme;
    }

    public function create(Dsn $dsn): EncrypterInterface&DecrypterInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn, [self::SCHEME]);
        }

        self::validateOptions($dsn, ['tenant', 'algorithm', 'wrap_algorithm', 'api_version', 'audience']);

        if (null === $dsn->host || '' === $dsn->host) {
            throw new InvalidArgumentException('The "azure-keyvault://" DSN must include the vault host (e.g. "azure-keyvault://<id>:<secret>@<name>.vault.azure.net?tenant=<tenant>").');
        }
        if (null === $dsn->user || '' === $dsn->user) {
            throw new InvalidArgumentException('The "azure-keyvault://" DSN must include the Azure AD client id in the user component.');
        }
        if (null === $dsn->password || '' === $dsn->password) {
            throw new InvalidArgumentException('The "azure-keyvault://" DSN must include the Azure AD client secret in the password component.');
        }

        $tenantId = $dsn->getRequiredOption('tenant');

        $port = null !== $dsn->port ? ':'.$dsn->port : '';
        $baseUri = 'https://'.$dsn->host.$port.'/';

        $audience = (string) ($dsn->getOption('audience') ?? self::inferAudience($dsn->host));

        $client = HttpClient::createForBaseUri($baseUri);

        return new AzureKeyVault(
            $client,
            new ClientCredentialsTokenProvider($client, (string) $tenantId, $dsn->user, $dsn->password, $audience),
            (string) $dsn->getOption('algorithm', 'RSA-OAEP-256'),
            (string) $dsn->getOption('wrap_algorithm', 'RSA-OAEP-256'),
            (string) $dsn->getOption('api_version', '7.4'),
        );
    }

    private static function inferAudience(string $host): string
    {
        foreach (self::MANAGED_HSM_HOST_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return self::MANAGED_HSM_AUDIENCE;
            }
        }

        return self::DEFAULT_AUDIENCE;
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
