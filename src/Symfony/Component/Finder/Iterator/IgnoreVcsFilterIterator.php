<?php

namespace Symfony\Component\Finder\Iterator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * IgnoreVcsFilterIterator filters out VCS files and directories.
 *
 * It currently supports Subversion, CVS, DARCS, Gnu Arch, Monotone, Bazaar-NG, GIT, and Mercurial.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
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
