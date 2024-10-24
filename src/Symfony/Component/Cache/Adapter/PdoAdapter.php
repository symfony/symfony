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

class PdoAdapter extends AbstractAdapter implements PruneableInterface
{
    use PdoTrait {
        createItemsTable as public createTable;
    }

    /**
     * You can either pass an existing database connection as PDO instance or
     * a DSN string that will be used to lazy-connect to the database when the
     * cache is actually used.
     *
     * List of available options:
     *  * db_table: The name of the table [default: cache_items]
     *  * db_id_col: The column where to store the cache id [default: item_id]
     *  * db_data_col: The column where to store the cache data [default: item_data]
     *  * db_lifetime_col: The column where to store the lifetime [default: item_lifetime]
     *  * db_time_col: The column where to store the timestamp [default: item_time]
     *  * db_username: The username when lazy-connect [default: '']
     *  * db_password: The password when lazy-connect [default: '']
     *  * db_connection_options: An array of driver-specific connection options [default: []]
     *
     * @throws InvalidArgumentException When first argument is not PDO nor Connection nor string
     * @throws InvalidArgumentException When PDO error mode is not PDO::ERRMODE_EXCEPTION
     * @throws InvalidArgumentException When namespace contains invalid characters
     */
    public function __construct(#[\SensitiveParameter] \PDO|string $connOrDsn, string $namespace = '', int $defaultLifetime = 0, array $options = [], ?MarshallerInterface $marshaller = null)
    {
        $this->init($connOrDsn, $namespace, $defaultLifetime, $options, $marshaller);
    }
}
