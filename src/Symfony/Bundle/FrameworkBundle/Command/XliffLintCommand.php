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

use Symfony\Component\Translation\Command\XliffLintCommand as BaseLintCommand;

/**
 * Validates XLIFF files syntax and outputs encountered errors.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @final since version 3.4
 */
class XliffLintCommand extends BaseLintCommand
{
    protected static $defaultName = 'lint:xliff';

    public function __construct($name = null, $directoryIteratorProvider = null, $isReadableProvider = null)
    {
        if (\func_num_args()) {
            @trigger_error(sprintf('Passing a constructor argument in "%s()" is deprecated since Symfony 3.4 and will be removed in 4.0. If the command was registered by convention, make it a service instead.', __METHOD__), E_USER_DEPRECATED);
        }

        if (null === $directoryIteratorProvider) {
            $directoryIteratorProvider = function ($directory, $default) {
                if (!is_dir($directory)) {
                    $directory = $this->getApplication()->getKernel()->locateResource($directory);
                }

                return $default($directory);
            };
        }

        if (null === $isReadableProvider) {
            $isReadableProvider = function ($fileOrDirectory, $default) {
                return 0 === strpos($fileOrDirectory, '@') || $default($fileOrDirectory);
            };
        }

        parent::__construct($name, $directoryIteratorProvider, $isReadableProvider);
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
