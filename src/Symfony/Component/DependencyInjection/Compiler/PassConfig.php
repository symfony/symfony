<?php

namespace Symfony\Component\DependencyInjection\Compiler;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Compiler Pass Configuration
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class PassConfig
{
    const TYPE_OPTIMIZE = 'optimization';
    const TYPE_REMOVE = 'removing';

    protected $mergePass;
    protected $optimizationPasses;
    protected $removingPasses;

    public function __construct()
    {
        $this->mergePass = new MergeExtensionConfigurationPass();

        $this->optimizationPasses = array(
            new ResolveParameterPlaceHoldersPass(),
            new ResolveReferencesToAliasesPass(),
            new ResolveInterfaceInjectorsPass(),
            new ResolveInvalidReferencesPass(),
        );

        $this->removingPasses = array(
            new RemovePrivateAliasesPass(),
            new ReplaceAliasByActualDefinitionPass(),
            new InlineServiceDefinitionsPass(),
            new RemoveUnusedDefinitionsPass(),
        );
    }

    public function getPasses()
    {
        return array_merge(
            array($this->mergePass),
            $this->optimizationPasses,
            $this->removingPasses
        );
    }

    public function addPass(CompilerPassInterface $pass, $type = self::TYPE_OPTIMIZE)
    {
        $property = $type.'Passes';
        if (!isset($this->$property)) {
            throw new \InvalidArgumentException(sprintf('Invalid type "%s".', $type));
        }

        $passes = &$this->$property;
        $passes[] = $pass;
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

    public function setOptimizationPasses(array $passes)
    {
        $this->optimizationPasses = $passes;
    }

    public function setRemovingPasses(array $passes)
    {
        $this->removingPasses = $passes;
    }
}