<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Security;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Util\SeedProviderInterface;

/**
 * Doctrine Seed Provider.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DoctrineSeedProvider implements SeedProviderInterface
{
    private $con;
    private $seedTableName;

    /**
     * Constructor.
     *
     * @param Connection $con
     * @param string     $tableName
     */
    public function __construct(Connection $con, $tableName)
    {
        $this->con = $con;
        $this->seedTableName = $tableName;
    }

    /**
     * {@inheritdoc}
     */
    public function loadSeed()
    {
        $stmt = $this->con->executeQuery("SELECT seed, updated_at FROM {$this->seedTableName}");

        if (false === $seed = $stmt->fetchColumn(0)) {
            throw new \RuntimeException('You need to initialize the generator by running the console command "init:prng".');
        }

        $seedLastUpdatedAt = new \DateTime($stmt->fetchColumn(1));

        return array($seed, $seedLastUpdatedAt);
    }

    /**
     * {@inheritdoc}
     */
    public function updateSeed($seed)
    {
        $params = array(':seed' => $seed, ':updatedAt' => new \DateTime());
        $types = array(':updatedAt' => Type::DATETIME);
        if (!$this->con->executeUpdate("UPDATE {$this->seedTableName} SET seed = :seed, updated_at = :updatedAt", $params, $types)) {
            $this->con->executeUpdate("INSERT INTO {$this->seedTableName} VALUES (:seed, :updatedAt)", $params, $types);
        }
    }
}
