<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle\Transformer;

use Symfony\Component\Intl\Exception\RuntimeException;
use Symfony\Component\Intl\ResourceBundle\Transformer\Rule\TransformationRuleInterface;
use Symfony\Component\Intl\ResourceBundle\Writer\PhpBundleWriter;

/**
 * Compiles a number of resource bundles based on predefined compilation rules.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class BundleTransformer
{
    /**
     * @var TransformationRuleInterface[]
     */
    private $rules = array();

    /**
     * Adds a new compilation rule.
     *
     * @param TransformationRuleInterface $rule The compilation rule.
     */
    public function addRule(TransformationRuleInterface $rule)
    {
        $this->rules[] = $rule;
    }

    /**
     * Runs the compilation with the given compilation context.
     *
     * @param CompilationContextInterface $context The context storing information
     *                                             needed to run the compilation.
     *
     * @throws RuntimeException If any of the files to be compiled by the loaded
     *                          compilation rules does not exist.
     */
    public function compileBundles(CompilationContextInterface $context)
    {
        $filesystem = $context->getFilesystem();
        $compiler = $context->getCompiler();

        $filesystem->remove($context->getBinaryDir());
        $filesystem->mkdir($context->getBinaryDir());

        foreach ($this->rules as $rule) {
            $filesystem->mkdir($context->getBinaryDir().'/'.$rule->getBundleName());

            $resources = (array) $rule->beforeCompile($context);

            foreach ($resources as $resource) {
                if (!file_exists($resource)) {
                    throw new RuntimeException(sprintf(
                        'The file "%s" to be compiled by %s does not exist.',
                        $resource,
                        get_class($rule)
                    ));
                }

                $compiler->compile($resource, $context->getBinaryDir().'/'.$rule->getBundleName());
            }

            $rule->afterCompile($context);
        }
    }

    public function createStubs(StubbingContextInterface $context)
    {
        $filesystem = $context->getFilesystem();
        $phpWriter = new PhpBundleWriter();

        $filesystem->remove($context->getStubDir());
        $filesystem->mkdir($context->getStubDir());

        foreach ($this->rules as $rule) {
            $filesystem->mkdir($context->getStubDir().'/'.$rule->getBundleName());

            $data = $rule->beforeCreateStub($context);

            $phpWriter->write($context->getStubDir().'/'.$rule->getBundleName(), 'en', $data);

            $rule->afterCreateStub($context);
        }
    }
}
