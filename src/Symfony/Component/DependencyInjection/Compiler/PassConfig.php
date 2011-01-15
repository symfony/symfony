<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

/**
 * Compiler Pass Configuration
 *
 * This class has a default configuration embedded.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class PassConfig
{
    const TYPE_AFTER_REMOVING = 'afterRemoving';
    const TYPE_BEFORE_OPTIMIZATION = 'beforeOptimization';
    const TYPE_BEFORE_REMOVING = 'beforeRemoving';
    const TYPE_OPTIMIZE = 'optimization';
    const TYPE_REMOVE = 'removing';

    protected $mergePass;
    protected $afterRemovingPasses;
    protected $beforeOptimizationPasses;
    protected $beforeRemovingPasses;
    protected $optimizationPasses;
    protected $removingPasses;

    public function __construct()
    {
        $this->mergePass = new MergeExtensionConfigurationPass();

        $this->afterRemovingPasses = array();
        $this->beforeOptimizationPasses = array();
        $this->beforeRemovingPasses = array();

        $this->optimizationPasses = array(
            new ResolveParameterPlaceHoldersPass(),
            new ResolveReferencesToAliasesPass(),
            new ResolveInterfaceInjectorsPass(),
            new ResolveInvalidReferencesPass(),
        );

        $this->removingPasses = array(
            new RemovePrivateAliasesPass(),
            new ReplaceAliasByActualDefinitionPass(),
            new RepeatedPass(array(
                new AnalyzeServiceReferencesPass(),
                new InlineServiceDefinitionsPass(),
                new AnalyzeServiceReferencesPass(),
                new RemoveUnusedDefinitionsPass(),
            )),
        );
    }

    public function getPasses()
    {
        return array_merge(
            array($this->mergePass),
            $this->beforeOptimizationPasses,
            $this->optimizationPasses,
            $this->beforeRemovingPasses,
            $this->removingPasses,
            $this->afterRemovingPasses
        );
    }

    public function addPass(CompilerPassInterface $pass, $type = self::TYPE_BEFORE_OPTIMIZATION)
    {
        $property = $type.'Passes';
        if (!isset($this->$property)) {
            throw new \InvalidArgumentException(sprintf('Invalid type "%s".', $type));
        }

        $passes = &$this->$property;
        $passes[] = $pass;
    }

    public function getBeforeOptimizationPasses()
    {
        return $this->beforeOptimizationPasses;
    }

    public function getBeforeRemovingPasses()
    {
        return $this->beforeRemovingPasses;
    }

    public function getOptimizationPasses()
    {
        return $this->optimizationPasses;
    }

    public function getRemovingPasses()
    {
        return $this->removingPasses;
    }

    public function getMergePass()
    {
        return $this->mergePass;
    }

    public function setMergePass(CompilerPassInterface $pass)
    {
        $this->mergePass = $pass;
    }

    public function setBeforeOptimizationPasses(array $passes)
    {
        $this->beforeOptimizationPasses = $passes;
    }

    public function setBeforeRemovingPasses(array $passes)
    {
        $this->beforeRemovingPasses = $passes;
    }

    public function setOptimizationPasses(array $passes)
    {
        $this->optimizationPasses = $passes;
    }

    public function setRemovingPasses(array $passes)
    {
        $this->removingPasses = $passes;
    }
}