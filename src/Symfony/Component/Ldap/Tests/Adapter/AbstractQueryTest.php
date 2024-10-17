<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Tests\Adapter;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\Ldap\Adapter\AbstractQuery;
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Adapter\ConnectionInterface;

class AbstractQueryTest extends TestCase
{
    use ExpectUserDeprecationMessageTrait;

    /**
     * @group legacy
     */
    public function testSizeLimitIsDeprecated()
    {
        $this->expectUserDeprecationMessage('Since symfony/ldap 7.2: The "sizeLimit" option is deprecated and will be removed in Symfony 8.0.');

        new class($this->createMock(ConnectionInterface::class), '', '', ['filter' => '*', 'sizeLimit' => 1]) extends AbstractQuery {
            public function execute(): CollectionInterface
            {
            }
        };
    }
}
