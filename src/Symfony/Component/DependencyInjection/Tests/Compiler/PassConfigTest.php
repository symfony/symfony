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

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * @author Guilhem N <egetick@gmail.com>
 */
class PassConfigTest extends \PHPUnit_Framework_TestCase
{
    public function testPassOrdering()
    {
        $config = new PassConfig();

        $pass1 = $this->getMock(CompilerPassInterface::class);
        $config->addPass($pass1, PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);

        $pass2 = $this->getMock(CompilerPassInterface::class);
        $config->addPass($pass2, PassConfig::TYPE_BEFORE_OPTIMIZATION, 30);

        $this->assertSame(array($pass2, $pass1), $config->getBeforeOptimizationPasses());
    }
}
