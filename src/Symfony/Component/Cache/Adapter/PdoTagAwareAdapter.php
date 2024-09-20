<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\Traits\PdoTrait;

/**
 * @author Yanick Witschi <yanick.witschi@terminal42.ch>
 */
class PdoTagAwareAdapter extends AbstractTagAwareAdapter implements PruneableInterface
{
    use PdoTrait {
        doSave as private doSaveItem;
        prune as private pruneItems;
    }

    /**
     * No need for a prefix here, should improve lookup time.
     */
    protected const TAGS_PREFIX = '';

    private string $tagsTable = 'cache_tags';
    private string $tagCol = 'item_tag';
    private string $tagIdxName = 'idx_cache_tags_item_tag';


    /**
     * You can either pass an existing database connection as PDO instance or
     * a DSN string that will be used to lazy-connect to the database when the
     * cache is actually used.
     *
     * List of available options:
     *  * db_table: The name of the cache item table [default: cache_items]
     *  * db_id_col: The column where to store the cache id [default: item_id]
     *  * db_data_col: The column where to store the cache data [default: item_data]
     *  * db_lifetime_col: The column where to store the lifetime [default: item_lifetime]
     *  * db_time_col: The column where to store the timestamp [default: item_time]
     *  * db_username: The username when lazy-connect [default: '']
     *  * db_password: The password when lazy-connect [default: '']
     *  * db_connection_options: An array of driver-specific connection options [default: []]
     *  * db_tags_table: The name of the tags table [default: cache_tags]
     *  * db_tags_col: The column where to store the tags [default: item_tag]
     *  * db_tags_tag_index_name: The index name for the tags column [default: idx_cache_tags_item_tag]
     *
     * @throws InvalidArgumentException When first argument is not PDO nor Connection nor string
     * @throws InvalidArgumentException When PDO error mode is not PDO::ERRMODE_EXCEPTION
     * @throws InvalidArgumentException When namespace contains invalid characters
     */
    public function __construct(#[\SensitiveParameter] \PDO|string $connOrDsn, string $namespace = '', int $defaultLifetime = 0, array $options = [], ?MarshallerInterface $marshaller = null)
    {
        $this->tagsTable = $options['db_tags_table'] ?? $this->tagsTable;
        $this->tagCol = $options['db_tags_col'] ?? $this->tagCol;
        $this->tagIdxName = $options['db_tags_tag_index_name'] ?? $this->tagIdxName;

        $this->init($connOrDsn, $namespace, $defaultLifetime, $options, $marshaller);
    }

    /**
     * Creates the tags table to store tag items which can be called once for setup.
     *
     * Both, cache ID and tag ID are saved in a column of maximum length 255 respecitvely.
     *
     * @throws \PDOException    When the table already exists
     * @throws \DomainException When an unsupported PDO driver is used
     */
    private function createTagsTable(): void
    {
        $sql = match ($driver = $this->getDriver()) {
            // We use varbinary for the ID column because it prevents unwanted conversions:
            // - character set conversions between server and client
            // - trailing space removal
            // - case-insensitivity
            // - language processing like é == e
            'mysql' => "CREATE TABLE $this->tagsTable ($this->idCol VARBINARY(255) NOT NULL, $this->tagCol VARBINARY(255) NOT NULL, PRIMARY KEY($this->idCol, $this->tagCol), INDEX $this->tagIdxName($this->tagCol)) COLLATE utf8mb4_bin, ENGINE = InnoDB",
            'sqlite' => "CREATE TABLE $this->tagsTable ($this->idCol TEXT NOT NULL, $this->tagCol TEXT NOT NULL, PRIMARY KEY ($this->idCol, $this->tagCol));CREATE INDEX $this->tagIdxName ON $this->tagsTable($this->tagCol)",
            'pgsql', 'sqlsrv' => "CREATE TABLE $this->tagsTable ($this->idCol VARCHAR(255) NOT NULL, $this->tagCol VARCHAR(255) NOT NULL, PRIMARY KEY($this->idCol, $this->tagCol);CREATE INDEX $this->tagIdxName ON $this->tagsTable($this->tagCol)",
            'oci' => "CREATE TABLE $this->tagsTable ($this->idCol VARCHAR2(255) NOT NULL, $this->tagCol VARCHAR2(255) NOT NULL, PRIMARY KEY($this->idCol, $this->tagCol);CREATE INDEX $this->tagIdxName ON $this->tagsTable($this->tagCol)",
            default => throw new \DomainException(\sprintf('Creating the cache table is currently not implemented for PDO driver "%s".', $driver)),
        };

        $this->getConnection()->exec($sql);
    }

    /**
     * Creates the tables to store cache items which can be called once for setup.
     *
     * Cache IDs are saved in a column of maximum length 255.
     * Cache data is saved in a BLOB.
     *
     * @throws \PDOException    When the table already exists
     * @throws \DomainException When an unsupported PDO driver is used
     */
    private function createTables(): void
    {
        $this->createItemsTable();
        $this->createTagsTable();
    }

    public function prune(): bool
    {
        return $this->pruneItems() && $this->pruneOrphanedTags();
    }

    protected function doSave(array $values, int $lifetime, array $addTagData = [], array $removeTagData = []): array
    {
        $failed = $this->doSaveItem($values, $lifetime);

        if (!\is_array($failed)) {
            return array_keys($values);
        }

        $driver = $this->getDriver();
        $insertSql = "INSERT INTO $this->tagsTable ($this->idCol, $this->tagCol) VALUES (:id, :tagId)";

        switch (true) {
            case 'mysql' === $driver:
                $sql = $insertSql." ON DUPLICATE KEY UPDATE $this->idCol = VALUES($this->idCol), $this->tagCol = VALUES($this->tagCol)";
                break;
            case 'oci' === $driver:
                // DUAL is Oracle specific dummy table
                $sql = "MERGE INTO $this->tagsTable USING DUAL ON ($this->idCol = :id, $this->tagCol = :tagId) ".
                    "WHEN NOT MATCHED THEN INSERT ($this->idCol, $this->tagCol) VALUES (:id, :tagId) ".
                    "WHEN MATCHED THEN UPDATE SET $this->idCol = :id, $this->tagCol = :tagId";
                break;
            case 'sqlsrv' === $driver && version_compare($this->getServerVersion(), '10', '>='):
                // MERGE is only available since SQL Server 2008 and must be terminated by semicolon
                // It also requires HOLDLOCK according to http://weblogs.sqlteam.com/dang/archive/2009/01/31/UPSERT-Race-Condition-With-MERGE.aspx
                $sql = "MERGE INTO $this->tagsTable WITH (HOLDLOCK) USING (SELECT 1 AS dummy) AS src ON ($this->idCol = :id, $this->tagCol = :tagId) ".
                    "WHEN NOT MATCHED THEN INSERT ($this->idCol, $this->tagCol) VALUES (:id, :tagId) ".
                    "WHEN MATCHED THEN UPDATE SET $this->idCol = :id, $this->tagCol = :tagId;";
                break;
            case 'sqlite' === $driver:
                $sql = 'INSERT OR REPLACE'.substr($insertSql, 6);
                break;
            case 'pgsql' === $driver && version_compare($this->getServerVersion(), '9.5', '>='):
                $sql = $insertSql." ON CONFLICT ($this->idCol, $this->tagCol) DO UPDATE SET ($this->idCol, $this->tagCol) = (EXCLUDED.$this->idCol, EXCLUDED.$this->tagCol)";
                break;
            default:
                throw new \DomainException(\sprintf('Caching support is currently not implemented for PDO driver "%s".', $driver));
        }

        foreach ($addTagData as $tagId => $ids) {
            foreach ($ids as $id) {
                if (\in_array($id, $failed, true)) {
                    continue;
                }

                try {
                    $stmt = $this->prepareStatementWithFallback($sql, function () {
                        $this->createTagsTable();
                    });

                    $stmt->bindParam(':id', $id);
                    $stmt->bindParam(':tagId', $tagId);

                    $this->executeStatementWithFallback($stmt, function () {
                        $this->createTagsTable();
                    });
                } catch (\PDOException $e) {
                    $failed[] = $id;
                }
            }
        }

        foreach ($removeTagData as $tagId => $ids) {
            foreach ($ids as $id) {
                if (\in_array($id, $failed, true)) {
                    continue;
                }

                $sql = "DELETE FROM $this->tagsTable WHERE $this->idCol=:id AND $this->tagCol=:tagId";

                try {
                    $stmt = $this->prepareStatementWithFallback($sql, function () {
                        $this->createTagsTable();
                    });

                    $stmt->bindParam(':id', $id);
                    $stmt->bindParam(':tagId', $tagId);

                    $this->executeStatementWithFallback($stmt, function () {
                        $this->createTagsTable();
                    });
                } catch (\PDOException $e) {
                    $failed[] = $id;
                }
            }
        }

        return $failed;
    }

    protected function doDeleteTagRelations(array $tagData): bool
    {
        foreach ($tagData as $tagId => $idList) {
            if ([] === $idList) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, \count($idList), '?'));
            $stmt = $this->prepareStatementWithFallback("DELETE FROM $this->tagsTable WHERE $this->tagCol=:tagId AND $this->idCol IN ($placeholders);", function () {
                $this->createTagsTable();
            });

            $stmt->bindValue(1, $tagId, \PDO::PARAM_STR);

            foreach ($idList as $index => $value) {
                $stmt->bindValue($index + 2, $value, \PDO::PARAM_STR);
            }
            $stmt->execute();
        }

        return true;
    }

    protected function doInvalidate(array $tagIds): bool
    {
        $placeholders = implode(',', array_fill(0, \count($tagIds), '?'));
        $stmt = $this->prepareStatementWithFallback("DELETE FROM $this->table WHERE $this->idCol IN (SELECT $this->idCol FROM $this->tagsTable WHERE $this->tagCol IN ($placeholders));", function () {
            $this->createTagsTable();
        });

        foreach ($tagIds as $index => $value) {
            $stmt->bindValue($index + 1, $value, \PDO::PARAM_STR);
        }
        $stmt->execute();

        return true;
    }

    /**
     * Prunes the tags table and removes all tags that are not used anywhere anymore.
     */
    private function pruneOrphanedTags(): bool
    {
        $conn = $this->getConnection();

        $stmt = $conn->prepare("DELETE FROM $this->tagsTable WHERE $this->idCol NOT IN (SELECT $this->idCol FROM $this->table)");
        $stmt->execute();

        return true;
    }
}
