<?php

namespace Symfony\Bundle\PropelBundle\Command;

use Symfony\Bundle\PropelBundle\Command\PhingCommand;
use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Symfony\Bundle\FrameworkBundle\Util\Filesystem;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * BuildCommand.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class BuildSqlCommand extends PhingCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDescription('Build the SQL generation code for all tables based on Propel XML schemas')
            ->setHelp(<<<EOT
The <info>propel:build-sql</info> command builds the SQL table generation code based on the XML schemas defined in all Bundles:

  <info>./symfony propel:build-sql</info>
EOT
            )
            ->setName('propel:build-sql')
        ;
    }

    /**
     * @see Command
     *
     * @throws \InvalidArgumentException When the target directory does not exist
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->callPhing('sql', array('propel.packageObjectModel' => false));
        $filesystem = new Filesystem();
        $basePath = $this->application->getKernel()->getRootDir(). DIRECTORY_SEPARATOR . 'propel'. DIRECTORY_SEPARATOR . 'sql';
        $sqlMap = file_get_contents($basePath . DIRECTORY_SEPARATOR . 'sqldb.map');
        foreach ($this->tempSchemas as $schemaFile => $schemaDetails) {
            $sqlFile = str_replace('.xml', '.sql', $schemaFile);
            $targetSqlFile = $schemaDetails['bundle'] . '-' . str_replace('.xml', '.sql', $schemaDetails['basename']);
            $targetSqlFilePath = $basePath . DIRECTORY_SEPARATOR . $targetSqlFile;
            $sqlMap = str_replace($sqlFile, $targetSqlFile, $sqlMap);
            $filesystem->remove($targetSqlFilePath);
            $filesystem->rename($basePath . DIRECTORY_SEPARATOR . $sqlFile, $targetSqlFilePath);
            $output->writeln(sprintf('Wrote SQL file for bundle "<info>%s</info>" in "<info>%s</info>"', $schemaDetails['bundle'], $targetSqlFilePath));
        }
        file_put_contents($basePath . DIRECTORY_SEPARATOR . 'sqldb.map', $sqlMap);
    }

}
