<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Util;

use Symfony\Component\HttpKernel\Log\LoggerInterface;

/**
 * A secure random number generator implementation.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
final class Prng
{
    private $logger;
    private $useOpenSsl;
    private $seed;
    private $seedUpdated;
    private $seedLastUpdatedAt;
    private $seedProvider;

    /**
     * Constructor.
     *
     * Be aware that a guessable seed will severely compromise the PRNG
     * algorithm that is employed.
     *
     * @param SeedProviderInterface $provider
     * @param LoggerInterface       $logger
     */
    public function __construct(SeedProviderInterface $provider = null, LoggerInterface $logger = null)
    {
        $this->seedProvider = $provider;
        $this->logger = $logger;

        // determine whether to use OpenSSL
        if (defined('PHP_WINDOWS_VERSION_BUILD') && version_compare(PHP_VERSION, '5.3.4', '<')) {
            $this->useOpenSsl = false;
        } elseif (!function_exists('openssl_random_pseudo_bytes')) {
            if (null !== $this->logger) {
                $this->logger->notice('It is recommended that you enable the "openssl" extension for random number generation.');
            }
            $this->useOpenSsl = false;
        } else {
            $this->useOpenSsl = true;
        }
    }

    /**
     * Generates the specified number of secure random bytes.
     *
     * @param integer $nbBytes
     * @return string
     */
    public function nextBytes($nbBytes)
    {
        // try OpenSSL
        if ($this->useOpenSsl) {
            $bytes = openssl_random_pseudo_bytes($nbBytes, $strong);

            if (false !== $bytes && true === $strong) {
                return $bytes;
            }

            if (null !== $this->logger) {
                $this->logger->info('OpenSSL did not produce a secure random number.');
            }
        }

        // initialize seed
        if (null === $this->seed) {
            if (null === $this->seedProvider) {
                throw new \RuntimeException('You need to specify a custom seed provider.');
            }

            list($this->seed, $this->seedLastUpdatedAt) = $this->seedProvider->loadSeed();
        }

        $bytes = '';
        while (strlen($bytes) < $nbBytes) {
            static $incr = 1;
            $bytes .= hash('sha512', $incr++.$this->seed.uniqid(mt_rand(), true).$nbBytes, true);
            $this->seed = base64_encode(hash('sha512', $this->seed.$bytes.$nbBytes, true));

            if (!$this->seedUpdated && $this->seedLastUpdatedAt->getTimestamp() < time() - mt_rand(1, 10)) {
                if (null !== $this->seedProvider) {
                    $this->seedProvider->updateSeed($this->seed);
                }

                $this->seedUpdated = true;
            }
        }

        return substr($bytes, 0, $nbBytes);
    }
}
