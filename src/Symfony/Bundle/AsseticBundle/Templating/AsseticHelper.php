<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Templating;

use Assetic\Factory\AssetFactory;
use Symfony\Component\Templating\Helper\Helper;

/**
 * The "assetic" templating helper.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 */
class AsseticHelper extends Helper
{
    protected $factory;
    protected $debug;
    protected $defaultJavascriptsOutput;
    protected $defaultStylesheetsOutput;

    /**
     * Constructor.
     *
     * @param AssetFactory $factory                  The asset factory
     * @param Boolean      $debug                    The debug mode
     * @param string       $defaultJavascriptsOutput The default {@link javascripts()} output string
     * @param string       $defaultStylesheetsOutput The default {@link stylesheets()} output string
     */
    public function __construct(AssetFactory $factory, $debug = false, $defaultJavascriptsOutput = 'js/*.js', $defaultStylesheetsOutput = 'css/*.css')
    {
        $this->factory = $factory;
        $this->debug = $debug;
        $this->defaultJavascriptsOutput = $defaultJavascriptsOutput;
        $this->defaultStylesheetsOutput = $defaultStylesheetsOutput;
    }

    /**
     * Gets the URLs for the configured asset.
     *
     * Usage looks something like this:
     *
     *     <?php foreach ($view['assetic']->assets('@jquery, js/src/core/*', '?yui_js') as $url): ?>
     *         <script src="<?php echo $url ?>" type="text/javascript"></script>
     *     <?php endforeach; ?>
     *
     * When in debug mode, the helper returns an array of one or more URLs.
     * When not in debug mode it returns an array of one URL.
     *
     * @param array|string $inputs  An array or comma-separated list of input strings
     * @param array|string $filters An array or comma-separated list of filter names
     * @param array        $options An array of options
     *
     * @return array An array of URLs for the asset
     */
    public function assets($inputs = array(), $filters = array(), array $options = array())
    {
        $explode = function($value)
        {
            return array_map('trim', explode(',', $value));
        };

        if (!is_array($inputs)) {
            $inputs = $explode($inputs);
        }

        if (!is_array($filters)) {
            $filters = $explode($filters);
        }

        if (!isset($options['debug'])) {
            $options['debug'] = $this->debug;
        }

        $coll = $this->factory->createAsset($inputs, $filters, $options);

        if (!$options['debug']) {
            return array($coll->getTargetUrl());
        }

        // create a pattern for each leaf's target url
        $pattern = $coll->getTargetUrl();
        if (false !== $pos = strrpos($pattern, '.')) {
            $pattern = substr($pattern, 0, $pos).'_*'.substr($pattern, $pos);
        } else {
            $pattern .= '_*';
        }

        $urls = array();
        foreach ($coll as $leaf) {
            $asset = $this->factory->createAsset($leaf->getSourceUrl(), $filters, array(
                'output' => $pattern,
                'name'   => 'part'.(count($urls) + 1),
                'debug'  => $options['debug'],
            ));
            $urls[] = $asset->getTargetUrl();
        }

        return $urls;
    }

    /**
     * Returns an array of javascript urls.
     *
     * This convenience method wraps {@link assets()} and provides a default
     * output string.
     */
    public function javascripts($inputs = array(), $filters = array(), array $options = array())
    {
        if (!isset($options['output'])) {
            $options['output'] = $this->defaultJavascriptsOutput;
        }

        return $this->assets($inputs, $filters, $options);
    }

    /**
     * Returns an array of stylesheet urls.
     *
     * This convenience method wraps {@link assets()} and provides a default
     * output string.
     */
    public function stylesheets($inputs = array(), $filters = array(), array $options = array())
    {
        if (!isset($options['output'])) {
            $options['output'] = $this->defaultStylesheetsOutput;
        }

        return $this->assets($inputs, $filters, $options);
    }

    public function getName()
    {
        return 'assetic';
    }
}
