<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\SecurityBundle\Command\SetAclCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\RoleSecurityIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Acl\Exception\NoAceFoundException;
use Symfony\Component\Security\Acl\Permission\BasicPermissionMap;

/**
 * Tests SetAclCommand.
 *
 * @author Kévin Dunglas <kevin@les-tilleuls.coop>
 * @requires extension pdo_sqlite
 * @group legacy
 */
class SetAclCommandTest extends AbstractWebTestCase
{
    const OBJECT_CLASS = 'Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\AclBundle\Entity\Car';
    const SECURITY_CLASS = 'Symfony\Component\Security\Core\User\User';

    public function testSetAclUser()
    {
        $objectId = 1;
        $securityUsername1 = 'kevin';
        $securityUsername2 = 'anne';
        $grantedPermission1 = 'VIEW';
        $grantedPermission2 = 'EDIT';

        $application = $this->getApplication();
        $application->add(new SetAclCommand());

        $setAclCommand = $application->find('acl:set');
        $setAclCommandTester = new CommandTester($setAclCommand);
        $setAclCommandTester->execute([
            'command' => 'acl:set',
            'arguments' => [$grantedPermission1, $grantedPermission2, sprintf('%s:%s', self::OBJECT_CLASS, $objectId)],
            '--user' => [sprintf('%s:%s', self::SECURITY_CLASS, $securityUsername1), sprintf('%s:%s', self::SECURITY_CLASS, $securityUsername2)],
        ]);

        $objectIdentity = new ObjectIdentity($objectId, self::OBJECT_CLASS);
        $securityIdentity1 = new UserSecurityIdentity($securityUsername1, self::SECURITY_CLASS);
        $securityIdentity2 = new UserSecurityIdentity($securityUsername2, self::SECURITY_CLASS);
        $permissionMap = new BasicPermissionMap();

        /** @var \Symfony\Component\Security\Acl\Model\AclProviderInterface $aclProvider */
        $aclProvider = $application->getKernel()->getContainer()->get('test.security.acl.provider');
        $acl = $aclProvider->findAcl($objectIdentity, [$securityIdentity1]);

        $this->assertTrue($acl->isGranted($permissionMap->getMasks($grantedPermission1, null), [$securityIdentity1]));
        $this->assertTrue($acl->isGranted($permissionMap->getMasks($grantedPermission1, null), [$securityIdentity2]));
        $this->assertTrue($acl->isGranted($permissionMap->getMasks($grantedPermission2, null), [$securityIdentity2]));

        try {
            $acl->isGranted($permissionMap->getMasks('OWNER', null), [$securityIdentity1]);
            $this->fail('NoAceFoundException not thrown');
        } catch (NoAceFoundException $e) {
        }

        try {
            $acl->isGranted($permissionMap->getMasks('OPERATOR', null), [$securityIdentity2]);
            $this->fail('NoAceFoundException not thrown');
        } catch (NoAceFoundException $e) {
        }
    }

    public function testSetAclRole()
    {
        $objectId = 1;
        $securityUsername = 'kevin';
        $grantedPermission = 'VIEW';
        $role = 'ROLE_ADMIN';

        $application = $this->getApplication();
        $application->add(new SetAclCommand($application->getKernel()->getContainer()->get('test.security.acl.provider')));

        $setAclCommand = $application->find('acl:set');
        $setAclCommandTester = new CommandTester($setAclCommand);
        $setAclCommandTester->execute([
            'command' => 'acl:set',
            'arguments' => [$grantedPermission, sprintf('%s:%s', str_replace('\\', '/', self::OBJECT_CLASS), $objectId)],
            '--role' => [$role],
        ]);

        $objectIdentity = new ObjectIdentity($objectId, self::OBJECT_CLASS);
        $userSecurityIdentity = new UserSecurityIdentity($securityUsername, self::SECURITY_CLASS);
        $roleSecurityIdentity = new RoleSecurityIdentity($role);
        $permissionMap = new BasicPermissionMap();

        /** @var \Symfony\Component\Security\Acl\Model\AclProviderInterface $aclProvider */
        $aclProvider = $application->getKernel()->getContainer()->get('test.security.acl.provider');
        $acl = $aclProvider->findAcl($objectIdentity, [$roleSecurityIdentity, $userSecurityIdentity]);

        $this->assertTrue($acl->isGranted($permissionMap->getMasks($grantedPermission, null), [$roleSecurityIdentity]));
        $this->assertTrue($acl->isGranted($permissionMap->getMasks($grantedPermission, null), [$roleSecurityIdentity]));

        try {
            $acl->isGranted($permissionMap->getMasks('VIEW', null), [$userSecurityIdentity]);
            $this->fail('NoAceFoundException not thrown');
        } catch (NoAceFoundException $e) {
        }

        try {
            $acl->isGranted($permissionMap->getMasks('OPERATOR', null), [$userSecurityIdentity]);
            $this->fail('NoAceFoundException not thrown');
        } catch (NoAceFoundException $e) {
        }
    }

    public function testSetAclClassScope()
    {
        $objectId = 1;
        $grantedPermission = 'VIEW';
        $role = 'ROLE_USER';

        $application = $this->getApplication();
        $application->add(new SetAclCommand($application->getKernel()->getContainer()->get('test.security.acl.provider')));

        $setAclCommand = $application->find('acl:set');
        $setAclCommandTester = new CommandTester($setAclCommand);
        $setAclCommandTester->execute([
            'command' => 'acl:set',
            'arguments' => [$grantedPermission, sprintf('%s:%s', self::OBJECT_CLASS, $objectId)],
            '--class-scope' => true,
            '--role' => [$role],
        ]);

        $objectIdentity1 = new ObjectIdentity($objectId, self::OBJECT_CLASS);
        $objectIdentity2 = new ObjectIdentity(2, self::OBJECT_CLASS);
        $roleSecurityIdentity = new RoleSecurityIdentity($role);
        $permissionMap = new BasicPermissionMap();

        /** @var \Symfony\Component\Security\Acl\Model\AclProviderInterface $aclProvider */
        $aclProvider = $application->getKernel()->getContainer()->get('test.security.acl.provider');

        $acl1 = $aclProvider->findAcl($objectIdentity1, [$roleSecurityIdentity]);
        $this->assertTrue($acl1->isGranted($permissionMap->getMasks($grantedPermission, null), [$roleSecurityIdentity]));

        $acl2 = $aclProvider->createAcl($objectIdentity2);
        $this->assertTrue($acl2->isGranted($permissionMap->getMasks($grantedPermission, null), [$roleSecurityIdentity]));
    }

    private function getApplication()
    {
        $kernel = $this->createKernel(['test_case' => 'Acl']);
        $kernel->boot();

        $application = new Application($kernel);

        $initAclCommand = $application->find('init:acl');
        $initAclCommandTester = new CommandTester($initAclCommand);
        $initAclCommandTester->execute(['command' => 'init:acl']);

        return $application;
    }
}
