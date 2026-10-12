<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Debug;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Debug\DebugItem;

class DebugItemTest extends TestCase
{
    #[DataProvider('provideMatches')]
    public function testMatches(bool $expected, string $query)
    {
        $item = new DebugItem('route', 'user_show', 'User Profile', searchText: '/users/{id} App\Controller\UserController');

        $this->assertSame($expected, $item->matches($query));
    }

    public static function provideMatches(): iterable
    {
        yield 'empty query matches everything' => [true, ''];
        yield 'matches the label' => [true, 'profile'];
        yield 'matches the label case-insensitively' => [true, 'PROFILE'];
        yield 'matches the value' => [true, 'user_show'];
        yield 'matches the extra search text' => [true, 'usercontroller'];
        yield 'matches the path in the search text' => [true, '/users/'];
        yield 'no match' => [false, 'mailer'];
    }

    public function testMatchesWithoutSearchText()
    {
        $item = new DebugItem('service', 'http_client', 'http_client');

        $this->assertTrue($item->matches('http'));
        $this->assertFalse($item->matches('controller'));
    }
}
