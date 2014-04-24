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

class PassConfigTest extends \PHPUnit_Framework_TestCase
{

    protected function getNewCompilerPassMock()
    {
        return $this->getMock('Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Compiler\PassConfig::getBeforeCustomization
     * @covers Symfony\Component\DependencyInjection\Compiler\PassConfig::setBeforeCustomization
     */
    public function testBeforeCustomizationPasses()
    {
        $passConfig = new PassConfig();
        $initialCount = count($passConfig->getBeforeCustomizationPasses());
        $passConfig->setBeforeCustomizationPasses(
            array_merge(
                $passConfig->getBeforeCustomizationPasses(),
                array($this->getNewCompilerPassMock())
            )
        );
        $this->assertCount($initialCount + 1, $passConfig->getBeforeCustomizationPasses());
        $passConfig->addPass($this->getNewCompilerPassMock(), PassConfig::TYPE_BEFORE_CUSTOMIZATION);
        $this->assertCount($initialCount + 2, $passConfig->getBeforeCustomizationPasses());
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Compiler\PassConfig::getCustomizationPasses
     * @covers Symfony\Component\DependencyInjection\Compiler\PassConfig::setCustomizationPasses
     */
    public function testCustomizationPasses()
    {
        $passConfig = new PassConfig();
        $initialCount = count($passConfig->getCustomizationPasses());
        $passConfig->setCustomizationPasses(
            array_merge(
                $passConfig->getCustomizationPasses(),
                array($this->getNewCompilerPassMock())
            )
        );
        $this->assertCount($initialCount + 1, $passConfig->getCustomizationPasses());
        $passConfig->addPass($this->getNewCompilerPassMock(), PassConfig::TYPE_CUSTOMIZE);
        $this->assertCount($initialCount + 2, $passConfig->getCustomizationPasses());
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Compiler\PassConfig::getAfterCustomizationPasses
     * @covers Symfony\Component\DependencyInjection\Compiler\PassConfig::setAfterCustomizationPasses
     */
    public function testAfterCustomizationPasses()
    {
        $passConfig = new PassConfig();
        $initialCount = count($passConfig->getAfterCustomizationPasses());
        $passConfig->setAfterCustomizationPasses(
            array_merge(
                $passConfig->getAfterCustomizationPasses(),
                array($this->getNewCompilerPassMock())
            )
        );
        $this->assertCount($initialCount + 1, $passConfig->getAfterCustomizationPasses());
        $passConfig->addPass($this->getNewCompilerPassMock(), PassConfig::TYPE_AFTER_CUSTOMIZATION);
        $this->assertCount($initialCount + 2, $passConfig->getAfterCustomizationPasses());
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Compiler\PassConfig::getBeforeOptimizationPasses
     * @covers Symfony\Component\DependencyInjection\Compiler\PassConfig::setBeforeOptimizationPasses
     * @covers Symfony\Component\DependencyInjection\Compiler\PassConfig::addPass
     */
    public function testBeforeOptimizationPasses()
    {
        $passConfig = new PassConfig();
        $initialCount = count($passConfig->getBeforeOptimizationPasses());
        $passConfig->setBeforeOptimizationPasses(
            array_merge(
                $passConfig->getBeforeOptimizationPasses(),
                array($this->getNewCompilerPassMock())
            )
        );
        $this->assertCount($initialCount + 1, $passConfig->getBeforeOptimizationPasses());
        $passConfig->addPass($this->getNewCompilerPassMock());
        $this->assertCount($initialCount + 2, $passConfig->getBeforeOptimizationPasses());
        $passConfig->addPass($this->getNewCompilerPassMock(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $this->assertCount($initialCount + 3, $passConfig->getBeforeOptimizationPasses());
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Compiler\PassConfig::getOptimizationPasses
     * @covers Symfony\Component\DependencyInjection\Compiler\PassConfig::setOptimizationPasses
     */
    public function testOptimizationPasses()
    {
        $passConfig = new PassConfig();
        $initialCount = count($passConfig->getOptimizationPasses());
        $passConfig->setOptimizationPasses(
            array_merge(
                $passConfig->getOptimizationPasses(),
                array($this->getNewCompilerPassMock())
            )
        );
        $this->assertCount($initialCount + 1, $passConfig->getOptimizationPasses());
        $passConfig->addPass($this->getNewCompilerPassMock(), PassConfig::TYPE_OPTIMIZE);
        $this->assertCount($initialCount + 2, $passConfig->getOptimizationPasses());
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Compiler\PassConfig::getBeforeRemovingPasses
     * @covers Symfony\Component\DependencyInjection\Compiler\PassConfig::setBeforeRemovingPasses
     */
    public function testBeforeRemovingPasses()
    {
        $passConfig = new PassConfig();
        $initialCount = count($passConfig->getBeforeRemovingPasses());
        $passConfig->setBeforeRemovingPasses(
            array_merge(
                $passConfig->getBeforeRemovingPasses(),
                array($this->getNewCompilerPassMock())
            )
        );
        $this->assertCount($initialCount + 1, $passConfig->getBeforeRemovingPasses());
        $passConfig->addPass($this->getNewCompilerPassMock(), PassConfig::TYPE_BEFORE_REMOVING);
        $this->assertCount($initialCount + 2, $passConfig->getBeforeRemovingPasses());
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Compiler\PassConfig::getRemovingPasses
     * @covers Symfony\Component\DependencyInjection\Compiler\PassConfig::setRemovingPasses
     */
    public function testRemovingPasses()
    {
        $passConfig = new PassConfig();
        $initialCount = count($passConfig->getRemovingPasses());
        $passConfig->setRemovingPasses(
            array_merge(
                $passConfig->getRemovingPasses(),
                array($this->getNewCompilerPassMock())
            )
        );
        $this->assertCount($initialCount + 1, $passConfig->getRemovingPasses());
        $passConfig->addPass($this->getNewCompilerPassMock(), PassConfig::TYPE_REMOVE);
        $this->assertCount($initialCount + 2, $passConfig->getRemovingPasses());
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Compiler\PassConfig::getAfterRemovingPasses
     * @covers Symfony\Component\DependencyInjection\Compiler\PassConfig::setAfterRemovingPasses
     */
    public function testAfterRemovingPasses()
    {
        $passConfig = new PassConfig();
        $initialCount = count($passConfig->getAfterRemovingPasses());
        $passConfig->setAfterRemovingPasses(
            array_merge(
                $passConfig->getAfterRemovingPasses(),
                array($this->getNewCompilerPassMock())
            )
        );
        $this->assertCount($initialCount + 1, $passConfig->getAfterRemovingPasses());
        $passConfig->addPass($this->getNewCompilerPassMock(), PassConfig::TYPE_AFTER_REMOVING);
        $this->assertCount($initialCount + 2, $passConfig->getAfterRemovingPasses());
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Compiler\PassConfig::addPasses
     * @covers Symfony\Component\DependencyInjection\Compiler\PassConfig::getPasses
     */
    public function testGetPasses()
    {
        $types = array(
            PassConfig::TYPE_BEFORE_CUSTOMIZATION,
            PassConfig::TYPE_CUSTOMIZE,
            PassConfig::TYPE_AFTER_CUSTOMIZATION,
            PassConfig::TYPE_AFTER_REMOVING,
            PassConfig::TYPE_BEFORE_OPTIMIZATION,
            PassConfig::TYPE_BEFORE_REMOVING,
            PassConfig::TYPE_OPTIMIZE,
            PassConfig::TYPE_REMOVE
        );

        $passConfig = new PassConfig();
        $count = count($passConfig->getPasses());

        foreach ($types as $type) {
            $passConfig->addPass($this->getNewCompilerPassMock(), $type);
            $this->assertCount(++$count, $passConfig->getPasses());
        }
    }
}
