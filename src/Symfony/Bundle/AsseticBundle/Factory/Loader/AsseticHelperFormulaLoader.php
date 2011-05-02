<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Factory\Loader;

use Assetic\Factory\Loader\BasePhpFormulaLoader;

/**
 * Loads formulae from Symfony2 PHP templates.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class AsseticHelperFormulaLoader extends BasePhpFormulaLoader
{
    protected function registerPrototypes()
    {
        return array(
            '$view[\'assetic\']->javascripts(*)'      => array('output' => 'js/*.js'),
            '$view[\'assetic\']->stylesheets(*)'      => array('output' => 'css/*.css'),
            '$view[\'assetic\']->image(*)'            => array('output' => 'images/*', 'single' => true),
            '$view["assetic"]->javascripts(*)'        => array('output' => 'js/*.js'),
            '$view["assetic"]->stylesheets(*)'        => array('output' => 'css/*.css'),
            '$view["assetic"]->image(*)'              => array('output' => 'images/*', 'single' => true),
            '$view->get(\'assetic\')->javascripts(*)' => array('output' => 'js/*.js'),
            '$view->get(\'assetic\')->stylesheets(*)' => array('output' => 'css/*.css'),
            '$view->get(\'assetic\')->image(*)'       => array('output' => 'images/*', 'single' => true),
            '$view->get("assetic")->javascripts(*)'   => array('output' => 'js/*.js'),
            '$view->get("assetic")->stylesheets(*)'   => array('output' => 'css/*.css'),
            '$view->get("assetic")->image(*)'         => array('output' => 'images/*', 'single' => true),
        );
    }

    protected function registerSetupCode()
    {
        return <<<'EOF'
class Helper
{
    public function assets()
    {
        global $_call;
        $_call = func_get_args();
    }

    public function javascripts()
    {
        global $_call;
        $_call = func_get_args();
    }

    public function stylesheets()
    {
        global $_call;
        $_call = func_get_args();
    }

    public function image()
    {
        global $_call;
        $_call = func_get_args();
    }
}

class View extends ArrayObject
{
    public function __construct(Helper $helper)
    {
        parent::__construct(array('assetic' => $helper));
    }

    public function get()
    {
        return $this['assetic'];
    }
}

$view = new View(new Helper());
EOF;
    }
}
