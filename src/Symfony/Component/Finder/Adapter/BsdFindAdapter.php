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

use Symfony\Component\Finder\Iterator;
use Symfony\Component\Finder\Shell\Shell;
use Symfony\Component\Finder\Shell\Command;

/**
 * Shell engine implementation using BSD find command.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class BsdFindAdapter extends AbstractFindAdapter
{
    /**
     * {@inheritdoc}
     */
    public function isSupported()
    {
        return in_array($this->shell->getType(), array(Shell::TYPE_BSD, Shell::TYPE_DARWIN)) && parent::isSupported();
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'bsd_find';
    }

    /**
     * {@inheritdoc}
     */
    protected function buildFormatSorting(Command $command, $format)
    {
        $command->get('find')->add('-print0 | xargs -0 stat -f')->arg($format.' %h/%f\\n')
            ->add('| sort | cut')->arg('-d ')->arg('-f2-');
    }
}
