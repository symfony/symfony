<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Node;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
abstract class AbstractNode implements NodeInterface
{
    private $nodeName;

    /**
     * @return string
     */
    public function getNodeName()
    {
        if (null === $this->nodeName) {
            $this->nodeName = preg_replace('~\\([\\d]+)(?:Node)?$~', '$1', __CLASS__);
        }

        return $this->nodeName;
    }

    /**
     * @param string $input
     *
     * @return string
     */
    protected function getLowerAscii($input)
    {
        return strtolower(iconv(mb_detect_encoding($input), 'us-ascii//TRANSLIT', $input));
    }
}
