<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests\Fixtures\IssueTriggerResolverIntegration;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\Tests\Fixtures\IssueTriggerResolver\ExtendsFinalParent;
use Symfony\Bridge\PhpUnit\Tests\Fixtures\IssueTriggerResolver\FinalParent;
use Symfony\Component\ErrorHandler\DebugClassLoader;

final class IssueTrigger extends TestCase
{
    public function testDebugClassLoaderDeprecation()
    {
        $files = [
            FinalParent::class => __DIR__.'/../IssueTriggerResolver/FinalParent.php',
            ExtendsFinalParent::class => __DIR__.'/../IssueTriggerResolver/ExtendsFinalParent.php',
        ];
        $loader = new DebugClassLoader(static function (string $class) use ($files): void {
            if (isset($files[$class])) {
                require $files[$class];
            }
        });
        $autoload = [$loader, 'loadClass'];
        spl_autoload_register($autoload, true, true);

        try {
            $this->assertTrue(class_exists(ExtendsFinalParent::class));
        } finally {
            spl_autoload_unregister($autoload);
        }
    }
}
