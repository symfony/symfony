<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Command;

@trigger_error(sprintf('Class "%s" is deprecated since Symfony 3.4 and will be removed in 4.0. Use Symfony\Bundle\AclBundle\Command\SetAclCommand instead.', SetAclCommand::class), \E_USER_DEPRECATED);

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\RoleSecurityIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Acl\Exception\AclAlreadyExistsException;
use Symfony\Component\Security\Acl\Model\MutableAclProviderInterface;
use Symfony\Component\Security\Acl\Permission\MaskBuilder;

/**
 * Sets ACL for objects.
 *
 * @author Kévin Dunglas <kevin@les-tilleuls.coop>
 *
 * @deprecated since version 3.4, to be removed in 4.0. See Symfony\Bundle\AclBundle\Command\SetAclCommand instead.
 */
class SetAclCommand extends ContainerAwareCommand
{
    protected static $defaultName = 'acl:set';

    private $provider;

    /**
     * @param MutableAclProviderInterface $provider
     */
    public function __construct($provider = null)
    {
        if (!$provider instanceof MutableAclProviderInterface) {
            parent::__construct($provider);

            return;
        }

        parent::__construct();

        $this->provider = $provider;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        if (null !== $this->provider) {
            return parent::isEnabled();
        }
        if (!$this->getContainer()->has('security.acl.provider')) {
            return false;
        }

        $provider = $this->getContainer()->get('security.acl.provider');
        if (!$provider instanceof MutableAclProviderInterface) {
            return false;
        }

        return parent::isEnabled();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Sets ACL for objects')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command sets ACL.
The ACL system must have been initialized with the <info>init:acl</info> command.

To set <comment>VIEW</comment> and <comment>EDIT</comment> permissions for the user <comment>kevin</comment> on the instance of
<comment>Acme\MyClass</comment> having the identifier <comment>42</comment>:

  <info>php %command.full_name% --user=Symfony/Component/Security/Core/User/User:kevin VIEW EDIT Acme/MyClass:42</info>

Note that you can use <comment>/</comment> instead of <comment>\\ </comment>for the namespace delimiter to avoid any
problem.

To set permissions for a role, use the <info>--role</info> option:

  <info>php %command.full_name% --role=ROLE_USER VIEW Acme/MyClass:1936</info>

To set permissions at the class scope, use the <info>--class-scope</info> option:

  <info>php %command.full_name% --class-scope --user=Symfony/Component/Security/Core/User/User:anne OWNER Acme/MyClass:42</info>
  
EOF
            )
            ->addArgument('arguments', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'A list of permissions and object identities (class name and ID separated by a column)')
            ->addOption('user', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A list of security identities')
            ->addOption('role', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A list of roles')
            ->addOption('class-scope', null, InputOption::VALUE_NONE, 'Use class-scope entries')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        (new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output))->warning('Command "acl:set" is deprecated since Symfony 3.4 and will be removed from SecurityBundle in 4.0. Install symfony/acl-bundle to use this command.');

        if (null === $this->provider) {
            $this->provider = $this->getContainer()->get('security.acl.provider');
        }

        // Parse arguments
        $objectIdentities = [];
        $maskBuilder = $this->getMaskBuilder();
        foreach ($input->getArgument('arguments') as $argument) {
            $data = explode(':', $argument, 2);

            if (\count($data) > 1) {
                $objectIdentities[] = new ObjectIdentity($data[1], strtr($data[0], '/', '\\'));
            } else {
                $maskBuilder->add($data[0]);
            }
        }

        // Build permissions mask
        $mask = $maskBuilder->get();

        $userOption = $input->getOption('user');
        $roleOption = $input->getOption('role');
        $classScopeOption = $input->getOption('class-scope');

        if (empty($userOption) && empty($roleOption)) {
            throw new InvalidArgumentException('A Role or a User must be specified.');
        }

        // Create security identities
        $securityIdentities = [];

        if ($userOption) {
            foreach ($userOption as $user) {
                $data = explode(':', $user, 2);

                if (1 === \count($data)) {
                    throw new InvalidArgumentException('The user must follow the format "Acme/MyUser:username".');
                }

                $securityIdentities[] = new UserSecurityIdentity($data[1], strtr($data[0], '/', '\\'));
            }
        }

        if ($roleOption) {
            foreach ($roleOption as $role) {
                $securityIdentities[] = new RoleSecurityIdentity($role);
            }
        }

        // Sets ACL
        foreach ($objectIdentities as $objectIdentity) {
            // Creates a new ACL if it does not already exist
            try {
                $this->provider->createAcl($objectIdentity);
            } catch (AclAlreadyExistsException $e) {
            }

            $acl = $this->provider->findAcl($objectIdentity, $securityIdentities);

            foreach ($securityIdentities as $securityIdentity) {
                if ($classScopeOption) {
                    $acl->insertClassAce($securityIdentity, $mask);
                } else {
                    $acl->insertObjectAce($securityIdentity, $mask);
                }
            }

            $this->provider->updateAcl($acl);
        }
    }

    /**
     * Gets the mask builder.
     *
     * @return MaskBuilder
     */
    protected function getMaskBuilder()
    {
        return new MaskBuilder();
    }
}
