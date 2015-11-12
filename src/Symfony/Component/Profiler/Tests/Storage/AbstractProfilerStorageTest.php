<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\Tests\Storage;

use Symfony\Component\Profiler\Profile;

/**
 * AbstractProfilerStorageTest.
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
abstract class AbstractProfilerStorageTest extends \PHPUnit_Framework_TestCase
{
    public function testStoreHttpProfile()
    {
        for ($i = 0; $i < 10; ++$i) {
            $profile = new Profile('token_'.$i);
            $this->getStorage()->write($profile, array(
                'ip' => '127.0.0.1',
                'url' => 'http://foo.bar',
                'method' => 'GET',
                'status_code' => 200
            ));
        }
        $this->assertCount(10, $this->getStorage()->findBy(array('ip' => '127.0.0.1', 'url' => 'http://foo.bar', 'method' => 'GET'), 20), sprintf('->write() stores data in the storage "%s"', get_class($this->getStorage())));
    }

    public function testStoreConsoleProfile()
    {
        for ($i = 0; $i < 10; ++$i) {
            $profile = new Profile('token_'.$i);
            $this->getStorage()->write($profile, array('command' => 'debug:test', 'result_code' => 1));
        }
        $this->assertCount(10, $this->getStorage()->findBy(array('command' => 'debug:test'), 10), sprintf('->write() stores data in the storage "%s"', get_class($this->getStorage())));
    }

    public function testChildren()
    {
        $parentProfile = new Profile('token_parent');
        $childProfile = new Profile('token_child');

        $parentProfile->addChild($childProfile);

        $this->getStorage()->write($parentProfile, array(
            'ip' => '127.0.0.1',
            'url' => 'http://foo.bar/parent',
            'method' => 'GET',
            'status_code' => 200
        ));

        // Load them from storage
        $parentProfile = $this->getStorage()->read('token_parent');
        /** @var Profile $childProfile */

        $childProfile = $this->getStorage()->read('token_child');

        // Check if childProfile is loaded
        $this->assertNotNull($childProfile);

        // Check child has link to parent
        $this->assertNotNull($childProfile->getParentToken());
        $this->assertEquals($parentProfile->getToken(), $childProfile->getParentToken());

        // Check parent has child
        $children = $parentProfile->getChildren();
        $this->assertCount(1, $children);
        $this->assertEquals($childProfile->getToken(), $children[0]->getToken());
    }

    public function testStoreSpecialCharsInUrl()
    {
        // The storage accepts special characters in URLs (Even though URLs are not
        // supposed to contain them)
        $this->getStorage()->write(new Profile('simple_quote'), array(
            'url' => 'http://foo.bar/\'',
        ));
        $this->assertTrue(false !== $this->getStorage()->read('simple_quote'), '->write() accepts single quotes in URL');

        $this->getStorage()->write(new Profile('double_quote'), array(
            'url' => 'http://foo.bar/"',
        ));
        $this->assertTrue(false !== $this->getStorage()->read('double_quote'), '->write() accepts double quotes in URL');

        $this->getStorage()->write(new Profile('backslash'), array(
            'url' => 'http://foo.bar/\\',
        ));
        $this->assertTrue(false !== $this->getStorage()->read('backslash'), '->write() accepts backslash in URL');

        $this->getStorage()->write(new Profile('comma'), array(
            'url' => 'http://foo.bar/,',
        ));
        $this->assertTrue(false !== $this->getStorage()->read('comma'), '->write() accepts comma in URL');
    }

    public function testStoreDuplicateToken()
    {
        $this->assertTrue($this->getStorage()->write(new Profile('token'), array()), '->write() returns true when the token is unique');
        $this->assertTrue($this->getStorage()->write(new Profile('token'), array()), '->write() returns true when the token is unique');

        $this->assertCount(1, $this->getStorage()->findBy(array(), 1000), '->findBy() does not return the same profile twice');
    }

    public function testRetrieveByIp()
    {
        $this->assertTrue($this->getStorage()->write(new Profile('token'), array(
            'ip' => '127.0.0.1',
        )), '->write() returns true when the token is unique');

        $this->assertCount(1, $this->getStorage()->findBy(array('ip' => '127.0.0.1'), 10), '->findBy() retrieve a record by IP');
        $this->assertCount(0, $this->getStorage()->findBy(array('ip' => '127.0.%.1'), 10), '->findBy() does not interpret a "%" as a wildcard in the IP');
        $this->assertCount(0, $this->getStorage()->findBy(array('ip' => '127.0._.1'), 10), '->findBy() does not interpret a "_" as a wildcard in the IP');
    }

    public function testRetrieveByUrl()
    {
        $this->getStorage()->write(new Profile('simple_quote'), array(
            'url' => 'http://foo.bar/\'',
        ));
        $this->assertCount(1, $this->getStorage()->findBy(array('url' => 'http://foo.bar/\''), 10), '->findBy() accepts single quotes in URLs');

        $this->getStorage()->write(new Profile('double_quote'), array(
            'url' => 'http://foo.bar/"',
        ));
        $this->assertCount(1, $this->getStorage()->findBy(array('url' => 'http://foo.bar/"'), 10), '->findBy() accepts double quotes in URLs');

        $this->getStorage()->write(new Profile('backslash'), array(
            'url' => 'http://foo\\bar/',
        ));
        $this->assertCount(1, $this->getStorage()->findBy(array('url' => 'http://foo\\bar/'), 10), '->findBy() accepts backslash in URLs');

        $this->getStorage()->write(new Profile('percent'), array(
            'url' => 'http://foo.bar/%',
        ));
        $this->assertCount(1, $this->getStorage()->findBy(array('url' => 'http://foo.bar/%'), 10), '->findBy() does not interpret a "%" as a wildcard in the URL');

        $this->getStorage()->write(new Profile('underscore'), array(
            'url' => 'http://foo.bar/_',
        ));
        $this->assertCount(1, $this->getStorage()->findBy(array('url' => 'http://foo.bar/_'), 10), '->findBy() does not interpret a "_" as a wildcard in the URL');

        $this->getStorage()->write(new Profile('semicolon'), array(
            'url' => 'http://foo.bar/;',
        ));
        $this->assertCount(1, $this->getStorage()->findBy(array('url' => 'http://foo.bar/;'), 10), '->findBy() accepts semicolon in URLs');
    }

    public function testStoreTime()
    {
        $dt = new \DateTime('now');
        $start = $dt->getTimestamp();

        for ($i = 0; $i < 3; ++$i) {
            $dt->modify('+1 minute');
            $this->getStorage()->write(new Profile('time_'.$i, $dt->getTimestamp()), array());
        }

        $records = $this->getStorage()->findBy(array(), 3, $start, time() + 3 * 60);
        $this->assertCount(3, $records, '->findBy() returns all previously added records');
        $this->assertEquals($records[0]['token'], 'time_2', '->findBy() returns records ordered by time in descendant order');
        $this->assertEquals($records[1]['token'], 'time_1', '->findBy() returns records ordered by time in descendant order');
        $this->assertEquals($records[2]['token'], 'time_0', '->findBy() returns records ordered by time in descendant order');

        $records = $this->getStorage()->findBy(array(), 3, $start, time() + 2 * 60);
        $this->assertCount(2, $records, '->findBy() should return only first two of the previously added records');
    }

    public function testRetrieveByEmptyCriteria()
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->getStorage()->write(new Profile('toke_'.$i), array());
        }
        $this->assertCount(5, $this->getStorage()->findBy(array(), 10), '->findBy() returns all previously added records');
        $this->getStorage()->purge();
    }

    public function testRetrieveByMethodAndLimit()
    {
        foreach (array('POST', 'GET') as $method) {
            for ($i = 0; $i < 5; ++$i) {
                $this->getStorage()->write( new Profile('token_'.$i.$method), array('method' => $method));
            }
        }

        $this->assertCount(5, $this->getStorage()->findBy(array('method' => 'POST'), 5));

        $this->getStorage()->purge();
    }

    public function testPurge()
    {
        $this->getStorage()->write(new Profile('token1'), array(
            'ip' => '127.0.0.1'
        ));

        $this->assertTrue(false !== $this->getStorage()->read('token1'));
        $this->assertCount(1, $this->getStorage()->findBy(array('ip' => '127.0.0.1'), 10));

        $this->getStorage()->write(new Profile('token2'), array(
            'ip' => '127.0.0.1'
        ));
        $this->assertTrue(false !== $this->getStorage()->read('token2'));
        $this->assertCount(2, $this->getStorage()->findBy(array('ip' => '127.0.0.1'), 10));

        $this->getStorage()->purge();

        $this->assertEmpty($this->getStorage()->read('token'), '->purge() removes all data stored by profiler');
        $this->assertCount(0, $this->getStorage()->findBy(array('ip' => '127.0.0.1'), 10), '->purge() removes all items from index');
    }

    public function testDuplicates()
    {
        for ($i = 1; $i <= 5; ++$i) {
            $profile = new Profile('token'.$i);

            ///three duplicates
            $this->getStorage()->write($profile, array(
                'ip' => '127.0.0.1',
                'url' => 'http://example.net',
            ));
            $this->getStorage()->write($profile, array(
                'ip' => '127.0.0.1',
                'url' => 'http://example.net',
            ));
            $this->getStorage()->write($profile, array(
                'ip' => '127.0.0.1',
                'url' => 'http://example.net',
            ));
        }
        $this->assertCount(3, $this->getStorage()->findBy(array('ip' => '127.0.0.1', 'url' => 'http://example.net'), 3), '->findBy() method returns incorrect number of entries');
    }

    public function testStatusCode()
    {
        $this->assertTrue($this->getStorage()->write(new Profile('token_200'), array(
            'status_code' => 200
        )), '->write() returns true when the token is unique');

        $this->assertTrue($this->getStorage()->write(new Profile('token_404'), array(
            'status_code' => 404
        )), '->write() returns true when the token is unique');

        $tokens = $this->getStorage()->findBy(array(), 10);
        $this->assertCount(2, $tokens);
        $this->assertContains($tokens[0]['status_code'], array(200, 404));
        $this->assertContains($tokens[1]['status_code'], array(200, 404));
    }

    /**
     * @return \Symfony\Component\Profiler\Storage\ProfilerStorageInterface
     */
    abstract protected function getStorage();
}
