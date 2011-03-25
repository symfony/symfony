<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
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
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 */
class AsseticHelper extends Helper
{
    protected $factory;
    protected $debug;
    protected $defaultJavascriptsOutput;
    protected $defaultStylesheetsOutput;
    protected $defaultImageOutput;

    /**
     * Constructor.
     *
     * @param AssetFactory $factory                  The asset factory
     * @param Boolean      $debug                    The debug mode
     * @param string       $defaultJavascriptsOutput The default {@link javascripts()} output string
     * @param string       $defaultStylesheetsOutput The default {@link stylesheets()} output string
     * @param string       $defaultImageOutput       The default {@link image()} output string
     */
    public function __construct(AssetFactory $factory, $debug = false, $defaultJavascriptsOutput = 'js/*.js', $defaultStylesheetsOutput = 'css/*.css', $defaultImageOutput = 'images/*')
    {
        $this->factory = $factory;
        $this->debug = $debug;
        $this->defaultJavascriptsOutput = $defaultJavascriptsOutput;
        $this->defaultStylesheetsOutput = $defaultStylesheetsOutput;
        $this->defaultImageOutput = $defaultImageOutput;
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

        return $this->getAssetUrls($inputs, $filters, $options);
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

        return $this->getAssetUrls($inputs, $filters, $options);
    }

    /**
     * Returns an array of one image url.
     *
     * This convenience method wraps {@link assets()} and provides a default
     * output string.
     */
    public function image($inputs = array(), $filters = array(), array $options = array())
    {
        if (!isset($options['output'])) {
            $options['output'] = $this->defaultImageOutput;
        }

        return $this->getAssetUrls($inputs, $filters, $options, true);
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
     * @param Boolean      $single  Use only the last input string
     *
     * @return array An array of URLs for the asset
     */
    private function getAssetUrls($inputs = array(), $filters = array(), array $options = array(), $single = false)
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

        if ($single && 1 < count($inputs)) {
            $inputs = array_slice($inputs, -1);
        }

        $coll = $this->factory->createAsset($inputs, $filters, $options);

        if (!$options['debug']) {
            return array($coll->getTargetUrl());
        }

        $urls = array();
        foreach ($coll as $leaf) {
            $urls[] = $leaf->getTargetUrl();
        }

        return $urls;
    }

    public function getName()
    {
        return 'assetic';
    }
}
