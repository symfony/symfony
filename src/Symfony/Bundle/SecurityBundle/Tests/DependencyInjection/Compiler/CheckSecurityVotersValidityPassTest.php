<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\AddSecurityVotersPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\CheckSecurityVotersValidityPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Tests\Authorization\Stub\VoterWithoutInterface;

class CheckSecurityVotersValidityPassTest extends TestCase
{
    /**
     * @group legacy
     * @expectedDeprecation Using a security.voter tag on a class without implementing the Symfony\Component\Security\Core\Authorization\Voter\VoterInterface is deprecated as of 3.4 and will be removed in 4.0. Implement the Symfony\Component\Security\Core\Authorization\Voter\VoterInterface instead.
     */
    public function testVoterMissingInterface()
    {
        $container = new ContainerBuilder();
        $container
            ->register('security.access.decision_manager', AccessDecisionManager::class)
            ->addArgument(array())
        ;
        $container
            ->register('without_interface', VoterWithoutInterface::class)
            ->addTag('security.voter')
        ;
        $addCompilerPass = new AddSecurityVotersPass();
        $addCompilerPass->process($container);
        $checkCompilerPass = new CheckSecurityVotersValidityPass();
        $checkCompilerPass->process($container);

        $argument = $container->getDefinition('security.access.decision_manager')->getArgument(0);
        $refs = $argument->getValues();
        $this->assertEquals(new Reference('without_interface'), $refs[0]);
        $this->assertCount(1, $refs);
    }

    /**
     * @group legacy
     */
    public function testVoterMissingInterfaceAndMethod()
    {
        $exception = LogicException::class;
        $message = 'stdClass should implement the Symfony\Component\Security\Core\Authorization\Voter\VoterInterface interface when used as voter.';

        if (method_exists($this, 'expectException')) {
            $this->expectException($exception);
            $this->expectExceptionMessage($message);
        } else {
            $this->setExpectedException($exception, $message);
        }

        $container = new ContainerBuilder();
        $container
            ->register('security.access.decision_manager', AccessDecisionManager::class)
            ->addArgument(array())
        ;
        $container
            ->register('without_method', 'stdClass')
            ->addTag('security.voter')
        ;
        $compilerPass = new CheckSecurityVotersValidityPass();
        $compilerPass->process($container);
    }
}
