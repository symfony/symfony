<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\PhpUnit\TextUI;

use PHPUnit\TextUI\Command as BaseCommand;

if (class_exists('PHPUnit_Runner_Version') && version_compare(\PHPUnit_Runner_Version::id(), '6.0.0', '<')) {
    class_alias('Symphony\Bridge\PhpUnit\Legacy\Command', 'Symphony\Bridge\PhpUnit\TextUI\Command');
} else {
    /**
     * {@inheritdoc}
     *
     * @internal
     */
    class Command extends BaseCommand
    {
        /**
         * {@inheritdoc}
         */
        protected function createRunner()
        {
            return new TestRunner($this->arguments['loader']);
        }
    }
}
