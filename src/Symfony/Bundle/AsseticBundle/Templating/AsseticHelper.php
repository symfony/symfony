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

use Assetic\Asset\AssetInterface;
use Assetic\Factory\AssetFactory;
use Symfony\Component\Templating\Helper\Helper;

/**
 * The "assetic" templating helper.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 */
abstract class AsseticHelper extends Helper
{
    protected $factory;
    protected $debug;
    protected $inputs;

    /**
     * Constructor.
     *
     * @param AssetFactory $factory The asset factory
     * @param Boolean      $debug   The debug mode
     */
    public function __construct(AssetFactory $factory, $debug = false)
    {
        $this->factory = $factory;
        $this->debug = $debug;
        $this->inputs = array();
    }

    /**
     * Adds an asset to an array.
     *
     * @param string $input The asset input
     * @param string $type  The asset type
     */
    public function add($input = null, $type = 'js')
    {
        if ($input && $type) {
            $this->inputs[$type] = trim($input);
        }
    }

    /**
     * Returns an array of javascript urls.
     */
    public function javascripts($inputs = array(), $filters = array(), array $options = array())
    {
        if (!$inputs || is_array($inputs) && count($inputs)) {
            $options['output'] = 'js/*';
        }

        if (!isset($options['output'])) {
            $options['output'] = 'js/*';
        }

        $options['type'] = 'js';

        return $this->getAssetUrls($inputs, $filters, $options);
    }

    /**
     * Returns an array of stylesheet urls.
     */
    public function stylesheets($inputs = array(), $filters = array(), array $options = array())
    {
        if (!isset($options['output'])) {
            $options['output'] = 'css/*';
        }

        $options['type'] = 'css';

        return $this->getAssetUrls($inputs, $filters, $options);
    }

    /**
     * Returns an array of one image url.
     */
    public function image($inputs = array(), $filters = array(), array $options = array())
    {
        if (!isset($options['output'])) {
            $options['output'] = 'images/*';
        }

        $options['single'] = true;
        $options['type'] = 'image';

        return $this->getAssetUrls($inputs, $filters, $options);
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
    private function getAssetUrls($inputs = array(), $filters = array(), array $options = array())
    {
        $explode = function($value)
        {
            return array_map('trim', explode(',', $value));
        };

        if (!is_array($inputs)) {
            $inputs = $explode($inputs);
        }

        if (count($inputs) === 0 && isset($options['type']) && isset($this->inputs[$options['type']])) {
            $inputs = $this->inputs[$options['type']];
        }

        if (!is_array($filters)) {
            $filters = $explode($filters);
        }

        if (!isset($options['debug'])) {
            $options['debug'] = $this->debug;
        }

        if (isset($options['single']) && $options['single'] && 1 < count($inputs)) {
            $inputs = array_slice($inputs, -1);
        }

        if (!isset($options['name'])) {
            $options['name'] = $this->factory->generateAssetName($inputs, $filters);
        }

        $coll = $this->factory->createAsset($inputs, $filters, $options);

        if (!$options['debug']) {
            return array($this->getAssetUrl($coll, $options));
        }

        $urls = array();
        foreach ($coll as $leaf) {
            $urls[] = $this->getAssetUrl($leaf, array_replace($options, array(
                'name' => $options['name'].'_'.count($urls),
            )));
        }

        if (isset($options['single']) && $options['single']) {
            return count($urls) > 0 ? $urls[0] : null;
        }

        return $urls;
    }

    /**
     * Returns an URL for the supplied asset.
     *
     * @param AssetInterface $asset   An asset
     * @param array          $options An array of options
     *
     * @return string An echo-ready URL
     */
    abstract protected function getAssetUrl(AssetInterface $asset, $options = array());

    public function getName()
    {
        return 'assetic';
    }
}
