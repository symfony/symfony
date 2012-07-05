<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Security;

use Symfony\Bridge\Doctrine\Security\DoctrineSeedProvider;
use Symfony\Bridge\Doctrine\Security\PrngSchema;
use Symfony\Component\Security\Core\Util\Prng;
use Symfony\Component\Security\Tests\Core\Util\PrngTest;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;

class DoctrineSeedProviderTest extends PrngTest
{
    public function getPrngs()
    {
        $con = DriverManager::getConnection(array(
            'driver' => 'pdo_sqlite',
            'memory' => true
        ));

        $schema = new PrngSchema('seed_table');
        foreach ($schema->toSql($con->getDatabasePlatform()) as $sql) {
            $con->executeQuery($sql);
        }
        $con->executeQuery("INSERT INTO seed_table VALUES (:seed, :updatedAt)", array(
            ':seed' => base64_encode(hash('sha512', uniqid(mt_rand(), true), true)),
            ':updatedAt' => date('Y-m-d H:i:s'),
        ));

        // no-openssl with database seed provider
        $prng = new Prng(new DoctrineSeedProvider($con, 'seed_table'));
        $this->disableOpenSsl($prng);

        $prngs = parent::getPrngs();
        $prngs[] = array($prng);

        return $prngs;
    }
}
