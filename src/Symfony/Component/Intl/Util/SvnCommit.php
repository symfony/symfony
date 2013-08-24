<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Util;

/**
 * An SVN commit.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @since v2.3.0
 */
class SvnCommit
{
    /**
     * @var \SimpleXMLElement
     */
    private $svnInfo;

    /**
     * Creates a commit from the given "svn info" data.
     *
     * @param \SimpleXMLElement $svnInfo The XML result from the "svn info"
     *                                   command.
     *
     * @since v2.3.0
     */
    public function __construct(\SimpleXMLElement $svnInfo)
    {
        $this->svnInfo = $svnInfo;
    }

    /**
     * Returns the revision of the commit.
     *
     * @return string The revision of the commit.
     *
     * @since v2.3.0
     */
    public function getRevision()
    {
        return (string) $this->svnInfo['revision'];
    }

    /**
     * Returns the author of the commit.
     *
     * @return string The author name.
     *
     * @since v2.3.0
     */
    public function getAuthor()
    {
        return (string) $this->svnInfo->author;
    }

    /**
     * Returns the date of the commit.
     *
     * @return string The commit date.
     *
     * @since v2.3.0
     */
    public function getDate()
    {
        return (string) $this->svnInfo->date;
    }
}
