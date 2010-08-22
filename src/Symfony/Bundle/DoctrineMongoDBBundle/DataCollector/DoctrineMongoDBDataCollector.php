<?php

namespace Symfony\Bundle\DoctrineMongoDBBundle\DataCollector;

use Symfony\Component\HttpKernel\Profiler\DataCollector\DataCollector;
use Symfony\Bundle\DoctrineMongoDBBundle\Logger\DoctrineMongoDBLogger;

/**
 * Data collector for the Doctrine MongoDB ODM.
 * 
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 */
class DoctrineMongoDBDataCollector extends DataCollector
{
    protected $logger;

    public function __construct(DoctrineMongoDBLogger $logger)
    {
        $this->logger = $logger;
    }

    public function collect()
    {
        $this->data['nb_queries'] = $this->logger->getNbQueries();
    }

    public function getSummary()
    {
        $color = $this->data['nb_queries'] < 10 ? '#2d2' : '#d22';

        return sprintf('<img style="margin-left: 10px; vertical-align: middle" alt="" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAeJJREFUeNqM009r1EAUAPD3kkm22bapthSlq/EPUg9FUMSLoCAVvPUm4smr4Ico+BW89SCe6tVDj2oVLwVRS6HqTcUqwe7W7SbZbGbmzTiTvei2ML6QDAwzP968l0GtNfwdS/eu1KMSBHEytbhf9HbLdrGFiPX8i9VP/6z3YCTKvITidwETyeTN5NbZNTzFHhVZGWc/MsjTfHT5QUBKCcFMOD97be6JRhgLj0fXxy9PrXi+h6jRDfCcQ3xx+iE0/ZbgAogrCE5Gd8JzzftaKjfgHw0uBUm0JCoOpAmUUqBIgwEe4LgfO4HwdLSoQoykJJBEYGuspQaMgwU227jhBHQDFwjMZmW+Wg0BPVyJR9hVJwAT3rQkCVJbxpzZ1M0ew9ZPVWrG3YVMDKTZSDYDZY+gzYuWAs2VdHfhW3/bFq/OoEYMYB8zUkd8dgMf8zWxV+XkKRA1YNL3EGhf9uSX/ksnQCl/X7zqPCZmAVkDyDzgH3qrtCe2nACGCMV6Z7n3pv2chT6wgEG1nW1UG91lDP7jT0TfA3NxurtPv96dfOu/Pp/Oves++36buPxl2zoabHSiGlTDS9Uv24PNbOXMheRE61hrpxE14LA4APjMr8eoOQY/03RTaLVTDkrggh8K/BFgAGj/AWtED/mBAAAAAElFTkSuQmCC" />
            <span style="color: %s">%d</span>
        ', $color, $this->data['nb_queries']);
    }

    public function getName()
    {
        return 'mongodb';
    }
}
