<?php

namespace Symfony\Framework\FoundationBundle\DataCollector;

use Symfony\Foundation\Kernel;
use Symfony\Components\HttpKernel\Profiler\DataCollector\DataCollector;
use Symfony\Components\DependencyInjection\ContainerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ConfigDataCollector.
 *
 * @package    Symfony
 * @subpackage Framework_FoundationBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ConfigDataCollector extends DataCollector
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function collect()
    {
        $kernel = $this->container->getKernelService();

        $this->data = array(
            'token'           => $this->profiler->getProfilerStorage()->getToken(),
            'symfony_version' => Kernel::VERSION,
            'name'            => $kernel->getName(),
            'env'             => $kernel->getEnvironment(),
            'debug'           => $kernel->isDebug(),
            'php_version'     => PHP_VERSION,
            'xdebug'          => extension_loaded('xdebug'),
            'accel'           => (
                (extension_loaded('eaccelerator') && ini_get('eaccelerator.enable'))
                ||
                (extension_loaded('apc') && ini_get('apc.enabled'))
                ||
                (extension_loaded('xcache') && ini_get('xcache.cacher'))
            ),
        );
    }

    public function getSummary()
    {
        return sprintf('<img style="vertical-align: middle" alt="Symfony" src="data:image/png;base64,R0lGODlhOwARAMQdAIZ1X9rVz0owD/Lx78K6r2JML3pnT+bj37asn6qej1Y+H+jk4JKDb0oxEJOEcId2YGJMMPTy8KufkLetoJ+RgNvW0M7Iv56Rf25aP8/JwG5aQP///z4jAP///wAAAAAAACH5BAEAAB0ALAAAAAA7ABEAAAX/YLc8WmmeaKqu7OosYsPNdG3fuCDgfM81C4dvyAsEiEgORZNMGjaI5rBEEzAugBmWBshiOICtAbu7QL+Ky8UwExQw6i8HTqPPqJzCYMMPCDYWNBYWBRsHfIaIgWYbWIiAHAF7iAkcZjSXd0wchwQYCBZ/gTODHAQbngEbARincBsYGAMDAK2NAKsGBodpG5i+mjMGewg7HJCki7AcT18YG1e+DBtZMweBjTO4r780eHmqAQXHoxylZs7Lz2q+6IIDxxczz6/GmRzfBQKnAxgWG+MiKUuXjh0zVDMKEYg3DxYueRwSAMO3SSKBfwMEPBtA4NDAOeoaZQp3alk2kBgEVJDk421TgX+AAu5SZClkwUwCEPA5wIYhSg4KYO5pieRZFClIrcELNkTAHgVJkUqEyOGBBCRdoiItUInGhAgQtIpNoqGD2Qxo06pdy7at27dvK5gNAQA7" />
            %s
        <img style="margin-left: 10px; vertical-align: middle" alt="PHP" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAARCAYAAAC8XK78AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAABfdJREFUeNqMVWlsFGUYfubas+y2hZZlWxahFlBKQbSg/ECUhCgQIsqRcMQfgHJoJAgRMcQ/KP5CJHgkCMQoERLBCyGIUGsIQlIOW44etGV7sle7x8zszOzMrO+MBU1AwiTfzux3vO/zPu/zvh+Tz+fxoKcr3DeMYZgpkqROSibl8fGENDqZzAbSolIoy5o7m81BVjRFltVkRlIihq50cIzSxDJKA/LKpb17P4g8yD5zPwB1Zy55fT7PC3nwC/qT6qxIVArQQDKlIqebMM08fD4fWJZFKpWiE3ds5GHZM3SNhgQ9l4xp2WitnI0dzcqpE98dPZR+IICvDpzkCgu9yw2D3xhJaNW3IzLkrA7dMMFxDDlkyAeDggIB8eh5yFIGleNmIzGgwVoaNAnG/mZtQKapQZGjGOhvborevr4rnogeqK+v1+4BsOvj7ys8HvfulMjOifdr0HUGgiDYxigFdmSmadjvEcN5bN0yF8FgCKvX7iMWcjRv2MN2zQr2Gdzlhc6bOaQG2nD92vHTbTevvNnd3XvDWuOtn23vH3zc4/L+FEsaFZqmw+EQUOARkcmEYZgmWcjTnAvFRWXIKl7097dBU3UMKwmQ0SZYW/yFpZQePznXIIsd0HI6HTPpP4fColKwXAmNx1AzLTBLVff/Smy+RKm8yG/ctH+ow+k6lJaECkotbWLg8bhwpf4gjh37Fh63CzzPQhRllJQGsWbdDnRHe2HtbfirHu1tjVCyKpwuL5YuexvFwwL4fM8Ge93tckJRFQqAx7z5y1A1aQkyeR9mPLe2vK+v+zCxNIOrfmLuZqejcAnD8oO5Y+B0Mjh39jD6+vqwddtneKpmAcrKylH3+0moSowMM2houIK167bi2ZmvIhAI4Pz5OmLIS1qgs+f+wKJFqzB/wVuYUPU0Wpov03otpk9/nhgtgMPpREnJI8VXG8+YrGFyizlOuKvkvI3cRHt7CyoqKsELY5CRSlA9+UVixo1wuAPNzY1wu50YNfoZ6OYolA6vtM8O8RWhP9Frf4+pnIpUZiiGlkxFdfUUkHwgSSkKkqXqUBEYUYnQqInzqZLYEP4jGI6YSCV7aaQIwAQixEVo/ejpvgpZzmLcuPHo7OxEKDQGuRxPoBhiqtU+GwyOQrjzJoqK/CgtrYCLUuAQJGLrIgq8btJMCIah20GyHEe6GR7kYeYiNDHkTvRerwtdt27afGSzIiJ95+yoThz/Bn6/HzNnzkZt7RlUTayhSiENMzlEbrfbAEaOLEdzUxPt86Gj7U8SrIZffjyBnp4eLF+xhnRRRLtEu0KsihIziRiv58QfdF3ZJAhuW7Uc2YxFw7bBCxfqcPnyWWhaDmPHVmHh4g10KG6norx8LIUhEGNZpFNRWyNW7atqFtGojP37thNAncAU47XXN+PRsXNAXdQmm+cdiMfC6Aw3niR3uU/ETGRxYVEoZOXH0FVC3GZv3PLuHqJqKPUDnvqPhxihJuQrxpb3viZnbqTSadIChwWvbEXBEB+artcRWA3Ll6/D5CfnERiVUuBFVhUGneepoTlIDwZOn9obVRRxJ7v/y3e6c1p6aTrVEzOpoCk43LrVRHQG4S4YjZTopk7HUe0rBEAiYQHpjOXctA1ad8FAygFRctpaoO6MspET0D/goj1OxBI5KmHJpt3h8JBuVBz7eWe6teXCCmpqN+92wpWrPqrheM+n5SPLa6KRZhLSMLB8iKLQ8TCPIHCUgG4kElGMKJsE64qwwFiOraitd2e4AbW/7Wvo6rq2nvyevecuWLlqh4/q4A2fP7De4fQHwXB2J/tHoP9eOPd7rFmeHPG8QGlQwJB31io5Smk81obGhlPRS/Unv9D13G6ylXjgbbhy5YdBhmVe5gT3QoejYKogeN08iZRlLNWzg31+cNyp4HzeFrE1DEOBJEapejqUrq7Gi92dN47E47eP0JXd+VDX8V0gq7eTr/w46m7TSKDVLMtXspwQYhlq7GC8Fg6TLiCTapucypomxRR5oEsUE62ZTH9jOp24kJGkptaWVuP/fPwtwAAItQDxq4a1ggAAAABJRU5ErkJggg==" />
            %s<span style="margin: 0; padding: 0; color: #aaa">/</span><span style="color: %s">xdebug</span><span style="margin: 0; padding: 0; color: #aaa">/</span><span style="color: %s">accel</span>
        <img style="margin-left: 10px; vertical-align: middle" alt="" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAATtJREFUeNqcU0Gqg0AMjZ/eQbEH0IUewIV4BXEjeD9dCvYGUlB056IU3Rf0Ci6c/hc6g/rr7+cHwkySl8xLZkZL05QORKz22jtAkiR0OkigIAjofD7T4/GgsizFLlcV/JLJAKOi4zjsME1zs8KPOHDrw7RXCwLBv0qWZZyLHMmAuq4jIcRHBW4taga3241c1+U9+r5erwrk+76kzri1gIEAII5jdcorWZMKW8aAA17OgRkYhkHLsvza9zoO/OYWQHmeZwYdFZIxKPDrGWhVVTGdKIrY6Xke1XWtrgo26EPyPN+8BTVEy7LU6bquUxiGPxigCHB939P+IZFt2x+vUOL2t8DSNA2D7vc7FUWxSYQNP/bAvXsH2jiO4nK5qMC3za1M08T2MAys+79wOvpxbdt+/I0ckL39V54CDAChFuDJX64gowAAAABJRU5ErkJggg==" />
            %s<span style="margin: 0; padding: 0; color: #aaa">/</span>%s<span style="margin: 0; padding: 0; color: #aaa">/</span>%s<span style="margin: 0; padding: 0; color: #aaa">/</span><a style="color: #000" href="#%s">%s</a>
        ', $this->data['symfony_version'], $this->data['php_version'], $this->data['xdebug'] ? '#3a3' : '#a33', $this->data['accel'] ? '#3a3' : '#a33', $this->data['name'], $this->data['env'], $this->data['debug'] ? 'debug' : 'no-debug', $this->data['token'], $this->data['token']);
    }

    public function getName()
    {
        return 'config';
    }
}
