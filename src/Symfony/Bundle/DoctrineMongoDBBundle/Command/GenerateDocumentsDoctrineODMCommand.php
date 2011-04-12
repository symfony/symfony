<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;

/**
 * Generate document classes from mapping information
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class GenerateDocumentsDoctrineODMCommand extends DoctrineODMCommand
{
    protected function configure()
    {
        $this
            ->setName('doctrine:mongodb:generate:documents')
            ->setDescription('Generate document classes and method stubs from your mapping information.')
            ->addArgument('bundle', InputArgument::REQUIRED, 'The bundle to initialize the document or documents in.')
            ->addOption('document', null, InputOption::VALUE_OPTIONAL, 'The document class to initialize (shortname without namespace).')
            ->setHelp(<<<EOT
The <info>doctrine:mongodb:generate:documents</info> command generates document classes and method stubs from your mapping information:

You have to limit generation of documents to an individual bundle:

  <info>./app/console doctrine:mongodb:generate:documents MyCustomBundle</info>

Alternatively, you can limit generation to a single document within a bundle:

  <info>./app/console doctrine:mongodb:generate:documents "MyCustomBundle" --document="User"</info>

You have to specify the shortname (without namespace) of the document you want to filter for.
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bundleName = $input->getArgument('bundle');
        $filterDocument = $input->getOption('document');

        $foundBundle = $this->findBundle($bundleName);

        if ($metadatas = $this->getBundleMetadatas($foundBundle)) {
            $output->writeln(sprintf('Generating documents for "<info>%s</info>"', $foundBundle->getName()));
            $documentGenerator = $this->getDocumentGenerator();

            foreach ($metadatas as $metadata) {
                if ($filterDocument && $metadata->reflClass->getShortName() == $filterDocument) {
                    continue;
                }

                if (strpos($metadata->name, $foundBundle->getNamespace()) === false) {
                    throw new \RuntimeException(
                        "Document " . $metadata->name . " and bundle don't have a common namespace, ".
                        "generation failed because the target directory cannot be detected.");
                }

                $output->writeln(sprintf('  > generating <comment>%s</comment>', $metadata->name));
                $documentGenerator->generate(array($metadata), $this->findBasePathForBundle($foundBundle));
            }
        } else {
            throw new \RuntimeException("Bundle " . $bundleName . " does not contain any mapped documents.");
        }
    }
}
