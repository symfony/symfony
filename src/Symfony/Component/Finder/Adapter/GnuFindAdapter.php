<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Adapter;

use Symfony\Component\Finder\Shell\Shell;
use Symfony\Component\Finder\Shell\Command;

/**
 * Shell engine implementation using GNU find command.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class GnuFindAdapter extends AbstractFindAdapter
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'gnu_find';
    }

    /**
     * {@inheritdoc}
     */
    protected function buildFormatSorting(Command $command, $format)
    {
        $command
            ->get('find')
            ->add('-printf')
            ->arg($format.' %h/%f\\n')
            ->add('| sort | cut')
            ->arg('-d ')
            ->arg('-f2-')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function canBeUsed()
    {
        return $this->shell->getType() === Shell::TYPE_UNIX && parent::canBeUsed();
    }

    /**
     * {@inheritdoc}
     */
    protected function buildFindCommand(Command $command, $dir)
    {
      return parent::buildFindCommand($command, $dir)->add('-regextype posix-extended');
    }
}
