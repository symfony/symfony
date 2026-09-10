<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Command\CachePoolClearCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;

class CachePoolClearCommandTest extends TestCase
{
    #[DataProvider('provideCompletionSuggestions')]
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $application = new Application();
        $application->addCommand(new CachePoolClearCommand(new Container(), new Psr6CacheClearer(['foo' => new ArrayAdapter()]), ['foo']));
        $tester = new CommandCompletionTester($application->get('cache:pool:clear'));

        $suggestions = $tester->complete($input);

        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public static function provideCompletionSuggestions(): iterable
    {
        yield 'pool_name' => [
            ['f'],
            ['foo'],
        ];
    }
}
