<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Dumps assets to the filesystem.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 */
class DumpCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('assetic:dump')
            ->setDescription('Dumps all assets to the filesystem')
            ->addArgument('base_dir', InputArgument::OPTIONAL, 'The base directory')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$baseDir = $input->getArgument('base_dir')) {
            $baseDir = $this->container->getParameter('assetic.document_root');
        }

        $am = $this->container->get('assetic.asset_manager');
        foreach ($am->all() as $name => $asset) {
            $output->writeln('<info>[asset]</info> '.$name);
            $asset->load();

            $target = $baseDir . '/' . $asset->getTargetUrl();
            if (!is_dir($dir = dirname($target))) {
                $output->writeln('<info>[dir+]</info> '.$dir);
                mkdir($dir);
            }

            $output->writeln('<info>[file+]</info> '.$asset->getTargetUrl());
            file_put_contents($target, $asset->dump());
        }
    }
}
