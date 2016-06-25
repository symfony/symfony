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
use Symfony\Bundle\SecurityBundle\Command\InitAclCommand;
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
 */
class SetAclCommandTest extends WebTestCase
{
    const OBJECT_CLASS = 'Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\AclBundle\Entity\Car';
    const SECURITY_CLASS = 'Symfony\Component\Security\Core\User\User';

    protected function setUp()
    {
        parent::setUp();

        $this->deleteTmpDir('Acl');
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->deleteTmpDir('Acl');
    }

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
        $setAclCommandTester->execute(array(
            'command' => 'acl:set',
            'arguments' => array($grantedPermission1, $grantedPermission2, sprintf('%s:%s', self::OBJECT_CLASS, $objectId)),
            '--user' => array(sprintf('%s:%s', self::SECURITY_CLASS, $securityUsername1), sprintf('%s:%s', self::SECURITY_CLASS, $securityUsername2)),
        ));

        $objectIdentity = new ObjectIdentity($objectId, self::OBJECT_CLASS);
        $securityIdentity1 = new UserSecurityIdentity($securityUsername1, self::SECURITY_CLASS);
        $securityIdentity2 = new UserSecurityIdentity($securityUsername2, self::SECURITY_CLASS);
        $permissionMap = new BasicPermissionMap();

        /** @var \Symfony\Component\Security\Acl\Model\AclProviderInterface $aclProvider */
        $aclProvider = $application->getKernel()->getContainer()->get('security.acl.provider');
        $acl = $aclProvider->findAcl($objectIdentity, array($securityIdentity1));

        $this->assertTrue($acl->isGranted($permissionMap->getMasks($grantedPermission1, null), array($securityIdentity1)));
        $this->assertTrue($acl->isGranted($permissionMap->getMasks($grantedPermission1, null), array($securityIdentity2)));
        $this->assertTrue($acl->isGranted($permissionMap->getMasks($grantedPermission2, null), array($securityIdentity2)));

        try {
            $acl->isGranted($permissionMap->getMasks('OWNER', null), array($securityIdentity1));
            $this->fail('NoAceFoundException not throwed');
        } catch (NoAceFoundException $e) {
        }

        try {
            $acl->isGranted($permissionMap->getMasks('OPERATOR', null), array($securityIdentity2));
            $this->fail('NoAceFoundException not throwed');
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
        $application->add(new SetAclCommand());

        $setAclCommand = $application->find('acl:set');
        $setAclCommandTester = new CommandTester($setAclCommand);
        $setAclCommandTester->execute(array(
            'command' => 'acl:set',
            'arguments' => array($grantedPermission, sprintf('%s:%s', str_replace('\\', '/', self::OBJECT_CLASS), $objectId)),
            '--role' => array($role),
        ));

        $objectIdentity = new ObjectIdentity($objectId, self::OBJECT_CLASS);
        $userSecurityIdentity = new UserSecurityIdentity($securityUsername, self::SECURITY_CLASS);
        $roleSecurityIdentity = new RoleSecurityIdentity($role);
        $permissionMap = new BasicPermissionMap();

        /** @var \Symfony\Component\Security\Acl\Model\AclProviderInterface $aclProvider */
        $aclProvider = $application->getKernel()->getContainer()->get('security.acl.provider');
        $acl = $aclProvider->findAcl($objectIdentity, array($roleSecurityIdentity, $userSecurityIdentity));

        $this->assertTrue($acl->isGranted($permissionMap->getMasks($grantedPermission, null), array($roleSecurityIdentity)));
        $this->assertTrue($acl->isGranted($permissionMap->getMasks($grantedPermission, null), array($roleSecurityIdentity)));

        try {
            $acl->isGranted($permissionMap->getMasks('VIEW', null), array($userSecurityIdentity));
            $this->fail('NoAceFoundException not throwed');
        } catch (NoAceFoundException $e) {
        }

        try {
            $acl->isGranted($permissionMap->getMasks('OPERATOR', null), array($userSecurityIdentity));
            $this->fail('NoAceFoundException not throwed');
        } catch (NoAceFoundException $e) {
        }
    }

    public function testSetAclClassScope()
    {
        $objectId = 1;
        $grantedPermission = 'VIEW';
        $role = 'ROLE_USER';

        $application = $this->getApplication();
        $application->add(new SetAclCommand());

        $setAclCommand = $application->find('acl:set');
        $setAclCommandTester = new CommandTester($setAclCommand);
        $setAclCommandTester->execute(array(
            'command' => 'acl:set',
            'arguments' => array($grantedPermission, sprintf('%s:%s', self::OBJECT_CLASS, $objectId)),
            '--class-scope' => true,
            '--role' => array($role),
        ));

        $objectIdentity1 = new ObjectIdentity($objectId, self::OBJECT_CLASS);
        $objectIdentity2 = new ObjectIdentity(2, self::OBJECT_CLASS);
        $roleSecurityIdentity = new RoleSecurityIdentity($role);
        $permissionMap = new BasicPermissionMap();

        /** @var \Symfony\Component\Security\Acl\Model\AclProviderInterface $aclProvider */
        $aclProvider = $application->getKernel()->getContainer()->get('security.acl.provider');

        $acl1 = $aclProvider->findAcl($objectIdentity1, array($roleSecurityIdentity));
        $this->assertTrue($acl1->isGranted($permissionMap->getMasks($grantedPermission, null), array($roleSecurityIdentity)));

        $acl2 = $aclProvider->createAcl($objectIdentity2);
        $this->assertTrue($acl2->isGranted($permissionMap->getMasks($grantedPermission, null), array($roleSecurityIdentity)));
    }

    private function getApplication()
    {
        $kernel = $this->createKernel(array('test_case' => 'Acl'));
        $kernel->boot();

        $application = new Application($kernel);
        $application->add(new InitAclCommand());

        $initAclCommand = $application->find('init:acl');
        $initAclCommandTester = new CommandTester($initAclCommand);
        $initAclCommandTester->execute(array('command' => 'init:acl'));

        return $application;
    }
}
