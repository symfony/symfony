<?php

namespace Symfony\Component\Console\Tests\Fixtures;

use Symfony\Component\Console\CommandGenerator\CommandGeneratorBase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Test1Command extends CommandGeneratorBase
{

    protected function configure()
    {

        $definition = $this->getCommandDefinition();
        $this
            ->setDescription($definition['description'])
        ;

        foreach ($definition['parameters'] as $param => $details) {
            $this->addArgument(
                $param,
                null,
                "Introduce a ${details['description']}."
            );

        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $arg = $input->getArgument('param1');
        $output->writeln(sprintf($arg));

    }
}
