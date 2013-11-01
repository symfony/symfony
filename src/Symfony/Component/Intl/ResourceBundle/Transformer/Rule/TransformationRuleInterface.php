<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle\Transformer\Rule;

use Symfony\Component\Intl\ResourceBundle\Transformer\CompilationContext;
use Symfony\Component\Intl\ResourceBundle\Transformer\StubbingContext;

/**
 * Contains instruction for compiling a resource bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface TransformationRuleInterface
{
    /**
     * Returns the name of the compiled resource bundle.
     *
     * @return string The name of the bundle.
     */
    public function getBundleName();

    /**
     * Runs instructions to be executed before compiling the sources of the
     * resource bundle.
     *
     * @param CompilationContext $context The contextual information of
     *                                             the compilation.
     *
     * @return string[] The source directories/files of the bundle.
     */
    public function beforeCompile(CompilationContext $context);

    /**
     * Runs instructions to be executed after compiling the sources of the
     * resource bundle.
     *
     * @param CompilationContext $context The contextual information of
     *                                             the compilation.
     */
    public function afterCompile(CompilationContext $context);

    /**
     * Runs instructions to be executed before creating the stub version of the
     * resource bundle.
     *
     * @param StubbingContext $context The contextual information of
     *                                          the compilation.
     *
     * @return mixed The data to include in the stub version.
     */
    public function beforeCreateStub(StubbingContext $context);

    /**
     * Runs instructions to be executed after creating the stub version of the
     * resource bundle.
     *
     * @param StubbingContext $context The contextual information of
     *                                          the compilation.
     */
    public function afterCreateStub(StubbingContext $context);
}
