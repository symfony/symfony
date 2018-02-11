<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Debug\AutowiringInfoManager;
use Symfony\Component\DependencyInjection\Debug\AutowiringTypeInfo;

/**
 * A console command for autowiring information.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 *
 * @internal
 */
class DebugAutowiringCommand extends ContainerDebugCommand
{
    protected static $defaultName = 'debug:autowiring';

    private $autowiringInfoManager;

    public function __construct(AutowiringInfoManager $autowiringInfoManager = null)
    {
        $this->autowiringInfoManager = $autowiringInfoManager;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('search', InputArgument::OPTIONAL, 'A search filter'),
            ))
            ->setDescription('Lists classes/interfaces you can use for autowiring')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays all classes and interfaces that
you can use as type-hints for autowiring:

  <info>php %command.full_name%</info>

You can also pass a search term to filter the list:

  <info>php %command.full_name% log</info>

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();

        $builder = $this->getContainerBuilder();
        $serviceIds = $builder->getServiceIds();
        $serviceIds = array_filter($serviceIds, array($this, 'filterToServiceTypes'));

        if ($search = $input->getArgument('search')) {
            $serviceIds = array_filter($serviceIds, function ($serviceId) use ($search) {
                return false !== stripos($serviceId, $search);
            });

            if (empty($serviceIds)) {
                $errorIo->error(sprintf('No autowirable classes or interfaces found matching "%s"', $search));

                return 1;
            }
        }

        asort($serviceIds);

        $io->text('The following classes & interfaces can be used as type-hints when autowiring:');
        if ($search) {
            $io->text(sprintf('(only showing classes/interfaces matching <comment>%s</comment>)', $search));
        }
        $io->newLine();

        $keyServices = array();
        $otherServices = array();
        $hasAlias = array();
        foreach ($serviceIds as $serviceId) {
            if (null !== $this->autowiringInfoManager && $autowiringInfo = $this->autowiringInfoManager->getInfo($serviceId)) {
                $keyServices[] = array(
                    'info' => $autowiringInfo,
                    'alias' => $builder->has($serviceId) ? $builder->getAlias($serviceId) : null,
                );

                continue;
            }

            if ($builder->hasAlias($serviceId)) {
                $hasAlias[(string) $builder->getAlias($serviceId)] = true;
            }

            $otherServices[$serviceId] = array(
                'type' => $serviceId,
                'alias' => $builder->hasAlias($serviceId) ? $builder->getAlias($serviceId) : null,
            );
        }
        $otherServices = array_diff_key($otherServices, $hasAlias);

        usort($keyServices, function ($a, $b) {
            if ($a['info']->getPriority() === $b['info']->getPriority()) {
                return 0;
            }

            return $a['info']->getPriority() > $b['info']->getPriority() ? -1 : 1;
        });

        $this->printKeyServices($keyServices, $io);

        $this->printOtherServices($otherServices, $io);
    }

    private function printOtherServices(array $otherServices, SymfonyStyle $io)
    {
        if (empty($otherServices)) {
            return;
        }

        // not necessary to print if this is the only list
        if (null !== $this->autowiringInfoManager) {
            $io->title('Other Services');
        }

        foreach ($otherServices as $serviceData) {
            $io->writeln(sprintf('<fg=cyan>%s</fg=cyan>', $serviceData['type']));
            if ($alias = $serviceData['alias']) {
                $io->writeln(sprintf('    alias to %s', $alias));
            }
        }
    }

    private function printKeyServices(array $keyServices, SymfonyStyle $io)
    {
        if (empty($keyServices)) {
            return;
        }

        $io->title('Key Services');
        foreach ($keyServices as $serviceData) {
            /** @var AutowiringTypeInfo $info */
            $info = $serviceData['info'];

            $nameLine = sprintf('<comment>%s</comment>', $info->getName());
            if ($info->getDescription()) {
                $nameLine .= sprintf(' (%s)', $info->getDescription());
            }
            $io->writeln($nameLine);

            $io->writeln(sprintf('    Type: <fg=cyan>%s</fg=cyan>', $info->getType()));

            if ($serviceData['alias']) {
                $io->writeln(sprintf('    Alias to the %s service', $serviceData['alias']));
            }

            $io->writeln('');
        }
    }
}
