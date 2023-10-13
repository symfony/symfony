<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Middleware\Debug\DBAL3;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bridge\Doctrine\Middleware\Debug\Query;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 *
 * @internal
 */
final class Connection extends AbstractConnectionMiddleware
{
    /** @var int */
    private $nestingLevel = 0;
    private $debugDataHolder;
    private $stopwatch;
    private $connectionName;

    public function __construct(
        ConnectionInterface $connection,
        DebugDataHolder $debugDataHolder,
        ?Stopwatch $stopwatch,
        string $connectionName
    ) {
        $this->connectionName = $connectionName;
        $this->stopwatch = $stopwatch;
        $this->debugDataHolder = $debugDataHolder;

        parent::__construct($connection);
    }

    public function prepare(string $sql): StatementInterface
    {
        return new Statement(
            parent::prepare($sql),
            $this->debugDataHolder,
            $this->connectionName,
            $sql,
            $this->stopwatch,
        );
    }

    public function query(string $sql): Result
    {
        $this->debugDataHolder->addQuery($this->connectionName, $query = new Query($sql));

        if ($this->stopwatch) {
            $this->stopwatch->start('doctrine', 'doctrine');
        }
        $query->start();

        try {
            return parent::query($sql);
        } finally {
            $query->stop();
            if ($this->stopwatch) {
                $this->stopwatch->stop('doctrine');
            }
        }
    }

    public function exec(string $sql): int
    {
        $this->debugDataHolder->addQuery($this->connectionName, $query = new Query($sql));

        if ($this->stopwatch) {
            $this->stopwatch->start('doctrine', 'doctrine');
        }
        $query->start();

        try {
            return parent::exec($sql);
        } finally {
            $query->stop();
            if ($this->stopwatch) {
                $this->stopwatch->stop('doctrine');
            }
        }
    }

    public function beginTransaction(): bool
    {
        $query = null;
        if (1 === ++$this->nestingLevel) {
            $this->debugDataHolder->addQuery($this->connectionName, $query = new Query('"START TRANSACTION"'));
        }

        if ($this->stopwatch) {
            $this->stopwatch->start('doctrine', 'doctrine');
        }
        if ($query) {
            $query->start();
        }

        try {
            return parent::beginTransaction();
        } finally {
            if ($query) {
                $query->stop();
            }
            if ($this->stopwatch) {
                $this->stopwatch->stop('doctrine');
            }
        }
    }

    public function commit(): bool
    {
        $query = null;
        if (1 === $this->nestingLevel--) {
            $this->debugDataHolder->addQuery($this->connectionName, $query = new Query('"COMMIT"'));
        }

        if ($this->stopwatch) {
            $this->stopwatch->start('doctrine', 'doctrine');
        }
        if ($query) {
            $query->start();
        }

        try {
            return parent::commit();
        } finally {
            if ($query) {
                $query->stop();
            }
            if ($this->stopwatch) {
                $this->stopwatch->stop('doctrine');
            }
        }
    }

    public function rollBack(): bool
    {
        $query = null;
        if (1 === $this->nestingLevel--) {
            $this->debugDataHolder->addQuery($this->connectionName, $query = new Query('"ROLLBACK"'));
        }

        if ($this->stopwatch) {
            $this->stopwatch->start('doctrine', 'doctrine');
        }
        if ($query) {
            $query->start();
        }

        try {
            return parent::rollBack();
        } finally {
            if ($query) {
                $query->stop();
            }
            if ($this->stopwatch) {
                $this->stopwatch->stop('doctrine');
            }
        }
    }
}
