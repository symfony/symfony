<?php

namespace Symfony\Bundle\FrameworkBundle\Templating\Asset;

use Symfony\Component\HttpFoundation\Request;

class BundlePackage extends PathPackage
{
    private $bundleDir;

    public function __construct(Request $request, $bundleName = null, $version = null, $format = null)
    {
        parent::__construct($request, $version, $format);
        $this->bundleDir = 'bundles/' . strtolower(str_replace('Bundle', '', $bundleName));
    }

    public function getUrl($path, $version = null)
    {
        if (isset($this->bundleDir))
            $path = $this->bundleDir . '/' . ltrim($path, '/');

        return parent::getUrl($path, $version);
    }

}
