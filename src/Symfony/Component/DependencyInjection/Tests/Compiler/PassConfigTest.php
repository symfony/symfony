<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;

/**
 * @author Guilhem N <egetick@gmail.com>
 */
class PassConfigTest extends TestCase
{
    public function testPassOrdering()
    {
        $config = new PassConfig();
        $config->setBeforeOptimizationPasses([]);

        $pass1 = self::createMock(CompilerPassInterface::class);
        $config->addPass($pass1, PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);

        $pass2 = self::createMock(CompilerPassInterface::class);
        $config->addPass($pass2, PassConfig::TYPE_BEFORE_OPTIMIZATION, 30);

        $passes = $config->getBeforeOptimizationPasses();
        self::assertSame($pass2, $passes[0]);
        self::assertSame($pass1, $passes[1]);
    }

    public function testPassOrderingWithoutPasses()
    {
        $config = new PassConfig();
        $config->setBeforeOptimizationPasses([]);
        $config->setAfterRemovingPasses([]);
        $config->setBeforeRemovingPasses([]);
        $config->setOptimizationPasses([]);
        $config->setRemovingPasses([]);

        self::assertEmpty($config->getBeforeOptimizationPasses());
        self::assertEmpty($config->getAfterRemovingPasses());
        self::assertEmpty($config->getBeforeRemovingPasses());
        self::assertEmpty($config->getOptimizationPasses());
        self::assertEmpty($config->getRemovingPasses());
    }
}
