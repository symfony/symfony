<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\XPath\Extension;

use Symfony\Component\CssSelector\XPath\Translator;

/**
 * XPath expression translator abstract extension.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class AbstractExtension implements ExtensionInterface
{
    /**
     * @var Translator
     */
    protected $translator;

    /**
     * Constructor.
     *
     * @param Translator $translator
     */
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function getNodeTranslators()
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function getCombinationTranslators()
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctionTranslators()
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function getPseudoClassTranslators()
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributeMatchingTranslators()
    {
        return array();
    }

    /**
     * Tests if given name is safe.
     *
     * @param string $name
     *
     * @return boolean
     */
    protected function isSafeName($name)
    {
        return 0 < preg_match('~^[a-zA-Z_][a-zA-Z0-9_.-]*$~', $name);
    }

    /**
     * Tests if given name is non whitespace.
     *
     * @param string $name
     *
     * @return boolean
     */
    protected function isNonWhitespace($name)
    {
        return 0 < preg_match('~^[^ \t\r\n\f]+$~', $name);
    }
}
