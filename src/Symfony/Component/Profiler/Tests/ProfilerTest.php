<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\Tests;

use Symfony\Component\Profiler\DataCollector\ConfigDataCollector;
use Symfony\Component\Profiler\DataCollector\MemoryDataCollector;
use Symfony\Component\Profiler\Profile;
use Symfony\Component\Profiler\Storage\FileProfilerStorage;
use Symfony\Component\Profiler\Storage\ProfilerStorageInterface;
use Symfony\Component\Profiler\Profiler;

/**
 * ProfilerTest.
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class ProfilerTest extends \PHPUnit_Framework_TestCase
{
    private $tmp;
    /** @var FileProfileStorage */
    private $storage;

    /** @var Profiler */
    private $profiler;

    public function testCollect()
    {
        $this->profiler->add(new ConfigDataCollector());
        $this->profiler->add(new MemoryDataCollector());

        $profile = $this->profiler->profile();

        $this->assertTrue($profile->has('config'));

        $this->assertFalse($profile->has('memory'));
    }

    public function testDisabledProfiler()
    {
        $this->profiler->add(new ConfigDataCollector());
        $this->profiler->add(new MemoryDataCollector());

        $this->profiler->disable();
        $this->assertNull($this->profiler->profile());

        $this->profiler->enable();
        $profile = $this->profiler->profile();

        $this->assertTrue($profile->has('config'));
    }

    public function testSave()
    {
        $storage = new DummyStorage();
        $profiler = new Profiler($storage);

        $profiler->add(new MemoryDataCollector());

        $profile = $profiler->profile();

        $this->assertTrue($profiler->save($profile, array()));

        $this->assertTrue($profile->has('memory'));
    }

    protected function setUp()
    {
        $this->tmp = tempnam(sys_get_temp_dir(), 'sf2_profiler');
        if (file_exists($this->tmp)) {
            @unlink($this->tmp);
        }

        $this->storage = new FileProfilerStorage('file:'.$this->tmp);
        $this->storage->purge();

        $this->profiler = new Profiler($this->storage);
    }

    protected function tearDown()
    {
        if (null !== $this->storage) {
            $this->storage->purge();
            $this->storage = null;

            @unlink($this->tmp);
        }
    }
}

class DummyStorage implements ProfilerStorageInterface
{
    protected $profiles = array();

    public function findBy(array $criteria = array(), $limit = null, $start = null, $end = null)
    {
        return $this->profiles;
    }

    public function read($token)
    {
        if (!isset($this->profiles[$token])) {
            return false;
        }

        return $this->profiles[$token];
    }

    public function write(Profile $profile, array $indexes)
    {
        if (isset($this->profiles[$profile->getToken()])) {
            return false;
        }
        $this->profiles[$profile->getToken()] = $profile;

        return true;
    }

    public function purge()
    {
        $this->profiles = array();
    }
}
