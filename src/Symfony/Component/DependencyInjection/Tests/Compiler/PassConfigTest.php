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

        $pass1 = $this->createMock(CompilerPassInterface::class);
        $config->addPass($pass1, PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);

        $pass2 = $this->createMock(CompilerPassInterface::class);
        $config->addPass($pass2, PassConfig::TYPE_BEFORE_OPTIMIZATION, 30);

        $passes = $config->getBeforeOptimizationPasses();
        $this->assertSame($pass2, $passes[0]);
        $this->assertSame($pass1, $passes[1]);
    }

    public function testPassOrderingWithoutPasses()
    {
        $config = new PassConfig();
        $config->setBeforeOptimizationPasses([]);
        $config->setAfterRemovingPasses([]);
        $config->setBeforeRemovingPasses([]);
        $config->setOptimizationPasses([]);
        $config->setRemovingPasses([]);

        $this->assertEmpty($config->getBeforeOptimizationPasses());
        $this->assertEmpty($config->getAfterRemovingPasses());
        $this->assertEmpty($config->getBeforeRemovingPasses());
        $this->assertEmpty($config->getOptimizationPasses());
        $this->assertEmpty($config->getRemovingPasses());
    }
}
