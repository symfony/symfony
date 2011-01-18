<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Loader;

use Symfony\Component\Templating\DebuggerInterface;

/**
 * Loader is the base class for all template loader classes.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Loader implements LoaderInterface
{
    protected $debugger;
    protected $nameParser;

    /**
     * Constructor.
     *
     * @param TemplateNameParserInterface $nameParser A TemplateNameParserInterface instance
     */
    public function __construct(TemplateNameParserInterface $nameParser)
    {
        $this->nameParser = $nameParser;
    }

    /**
     * Gets the template name parser.
     *
     * @return TemplateNameParserInterface A TemplateNameParserInterface instance
     */
    public function getTemplateNameParser()
    {
        return $this->nameParser;
    }

    /**
     * Sets the debugger to use for this loader.
     *
     * @param DebuggerInterface $debugger A debugger instance
     */
    public function setDebugger(DebuggerInterface $debugger)
    {
        $this->debugger = $debugger;
    }
}
