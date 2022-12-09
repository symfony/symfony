<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Esendex\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Esendex\EsendexOptions;

class EsendexOptionsTest extends TestCase
{
    public function testEsendexOptions()
    {
        $esendexOptions = (new EsendexOptions())->setFrom('test_from')->setAccountReference('test_account_reference')->setRecipientId('test_recipient');

        self::assertSame([
            'from' => 'test_from',
            'account_reference' => 'test_account_reference',
        ], $esendexOptions->toArray());
    }
}
