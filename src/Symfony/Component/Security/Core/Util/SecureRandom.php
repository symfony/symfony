<?php

namespace Symfony\Component\Security\Core\Util;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

/**
 * A secure random number generator implementation.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
final class SecureRandom
{
    private $logger;
    private $useOpenSsl;
    private $con;
    private $seed;
    private $seedTableName;
    private $seedUpdated;
    private $seedLastUpdatedAt;
    private $seedProvider;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;

        // determine whether to use OpenSSL
        if (0 === stripos(PHP_OS, 'win')) {
            $this->useOpenSsl = false;
        } else if (!function_exists('openssl_random_pseudo_bytes')) {
            $this->logger->notice('It is recommended that you enable the "openssl" extension for random number generation.');
            $this->useOpenSsl = false;
        } else {
            $this->useOpenSsl = true;
        }
    }

    /**
     * Sets the Doctrine seed provider.
     *
     * @param Connection $con
     * @param string $tableName
     */
    public function setConnection(Connection $con, $tableName)
    {
        $this->con = $con;
        $this->seedTableName = $tableName;
    }

    /**
     * Sets a custom seed provider implementation.
     *
     * Be aware that a guessable seed will severely compromise the PRNG
     * algorithm that is employed.
     *
     * @param SeedProviderInterface $provider
     */
    public function setSeedProvider(SeedProviderInterface $provider)
    {
        $this->seedProvider = $provider;
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
            $strong = false;
            $bytes = openssl_random_pseudo_bytes($nbBytes, $strong);

            if (false !== $bytes && true === $strong) {
                return $bytes;
            }

            $this->logger->info('OpenSSL did not produce a secure random number.');
        }

        // initialize seed
        if (null === $this->seed) {
            if (null !== $this->seedProvider) {
                list($this->seed, $this->seedLastUpdatedAt) = $this->seedProvider->loadSeed();
            } else if (null !== $this->con) {
                $this->initializeSeedFromDatabase();
            } else {
                throw new \RuntimeException('You need to either specify a database connection, or a custom seed provider.');
            }
        }

        $bytes = '';
        while (strlen($bytes) < $nbBytes) {
            static $incr = 1;
            $bytes .= hash('sha512', $incr++.$this->seed.uniqid(mt_rand(), true).$nbBytes, true);
            $this->seed = base64_encode(hash('sha512', $this->seed.$bytes.$nbBytes, true));

            if (!$this->seedUpdated && $this->seedLastUpdatedAt->getTimestamp() < time() - mt_rand(1, 10)) {
                if (null !== $this->seedProvider) {
                    $this->seedProvider->updateSeed($this->seed);
                } else if (null !== $this->con) {
                    $this->saveSeedToDatabase();
                }

                $this->seedUpdated = true;
            }
        }

        return substr($bytes, 0, $nbBytes);
    }

    private function saveSeedToDatabase()
    {
        $this->con->executeQuery("UPDATE {$this->seedTableName} SET seed = :seed, updated_at = :updatedAt", array(
            ':seed' => $this->seed,
            ':updatedAt' => new \DateTime(),
        ), array(
            ':updatedAt' => Type::DATETIME,
        ));
    }

    private function initializeSeedFromDatabase()
    {
        $stmt = $this->con->executeQuery("SELECT seed, updated_at FROM {$this->seedTableName}");

        if (false === $this->seed = $stmt->fetchColumn(0)) {
            throw new \RuntimeException('The seeding table for the SPRNG was not initialized.');
        }

        $this->seedLastUpdatedAt = new \DateTime($stmt->fetchColumn(1));
    }
}