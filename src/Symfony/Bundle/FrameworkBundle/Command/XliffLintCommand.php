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
use Symfony\Component\Translation\Command\XliffLintCommand as BaseLintCommand;

/**
 * Validates XLIFF files syntax and outputs encountered errors.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @final
 */
#[AsCommand(name: 'lint:xliff', description: 'Lints an XLIFF file and outputs encountered errors')]
class XliffLintCommand extends BaseLintCommand
{
    public function __construct()
    {
        $directoryIteratorProvider = function ($directory, $default) {
            if (!is_dir($directory)) {
                $directory = $this->getApplication()->getKernel()->locateResource($directory);
            }

            return $default($directory);
        };

        $isReadableProvider = function ($fileOrDirectory, $default) {
            return str_starts_with($fileOrDirectory, '@') || $default($fileOrDirectory);
        };

        parent::__construct(null, $directoryIteratorProvider, $isReadableProvider);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setHelp($this->getHelp().<<<'EOF'

Or find all files in a bundle:

  <info>php %command.full_name% @AcmeDemoBundle</info>

EOF
            );
    }
}
