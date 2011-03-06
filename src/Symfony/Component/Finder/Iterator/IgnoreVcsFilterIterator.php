<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Iterator;

/**
 * IgnoreVcsFilterIterator filters out VCS files and directories.
 *
 * It currently supports Subversion, CVS, DARCS, Gnu Arch, Monotone, Bazaar-NG, GIT, and Mercurial.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class IgnoreVcsFilterIterator extends ExcludeDirectoryFilterIterator
{
    /**
     * Constructor.
     *
     * @param \Iterator $iterator The Iterator to filter
     */
    public function __construct(\Iterator $iterator)
    {
        parent::__construct($iterator, array('.svn', '_svn', 'CVS', '_darcs', '.arch-params', '.monotone', '.bzr', '.git', '.hg'));
    }
}
