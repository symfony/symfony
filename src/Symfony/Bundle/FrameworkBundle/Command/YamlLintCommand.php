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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Yaml\Command\LintCommand as BaseLintCommand;
use Symfony\Component\Yaml\Schema\SchemaResolverInterface;
use Symfony\Component\Yaml\Schema\SchemaValidatorInterface;

/**
 * Validates YAML files syntax and outputs encountered errors.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @final
 */
#[AsCommand(name: 'lint:yaml', description: 'Lint a YAML file and outputs encountered errors')]
class YamlLintCommand extends BaseLintCommand
{
    public function __construct(?SchemaResolverInterface $schemaResolver = null, ?SchemaValidatorInterface $schemaValidator = null, ?string $projectDir = null)
    {
        $directoryIteratorProvider = function ($directory, $default) {
            if (!is_dir($directory)) {
                $directory = $this->getApplication()->getKernel()->locateResource($directory);
            }

            return $default($directory);
        };

        $isReadableProvider = static fn ($fileOrDirectory, $default) => str_starts_with($fileOrDirectory, '@') || $default($fileOrDirectory);

        parent::__construct(null, $directoryIteratorProvider, $isReadableProvider, $schemaResolver, $schemaValidator, $projectDir);
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setHelp($this->getHelp().<<<'EOF'

            Or find all files in a bundle:

              <info>php %command.full_name% @AcmeDemoBundle</info>

            EOF
        );
    }
}
