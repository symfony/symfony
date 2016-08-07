<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\TextUI;

/**
 * {@inheritdoc}
 */
class Command extends \PHPUnit_TextUI_Command
{
    /**
     * {@inheritdoc}
     */
    protected function createRunner()
    {
        return new TestRunner($this->arguments['loader']);
    }

    /**
     * {@inheritdoc}
     */
    protected function handleBootstrap($filename)
    {
        parent::handleBootstrap($filename);

        // By default, we want PHPUnit's autoloader before Symfony's one
        if (!getenv('SYMFONY_PHPUNIT_OVERLOAD')) {
            $filename = realpath(stream_resolve_include_path($filename));
            $symfonyLoader = realpath(dirname(PHPUNIT_COMPOSER_INSTALL).'/../../../vendor/autoload.php');

            if ($filename === $symfonyLoader) {
                $symfonyLoader = require $symfonyLoader;
                $symfonyLoader->unregister();
                $symfonyLoader->register(false);
            }
        }
    }
}
