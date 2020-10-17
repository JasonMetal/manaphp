<?php

namespace Tests;

use ManaPHP\Db\Adapter\Mysql;
use ManaPHP\Di;
use ManaPHP\Di\FactoryDefault;
use PHPUnit\Framework\TestCase;

class CacheAdapterDbTest extends TestCase
{
    public function setUp()
    {
        $di = new FactoryDefault();
        $di->alias->set('@data', __DIR__ . '/tmp/data');
        $di->setShared(
            'db', function () {
            $config = require __DIR__ . '/config.database.php';
            $db = new Mysql($config['mysql']);
            $db->attachEvent(
                'db:beforeQuery', function (\ManaPHP\DbInterface $source, $data) {
                //  var_dump(['sql'=>$source->getSQL(),'bind'=>$source->getBind()]);
                var_dump($source->getSQL(), $source->getEmulatedSQL(2));

            }
            );
            return $db;
        }
        );
    }

    public function test_exists()
    {
        $cache = Di::getDefault()->get('ManaPHP\Cache\Adapter\Db');

        $cache->delete('var');
        $this->assertFalse($cache->exists('var'));
        $cache->set('var', 'value', 1000);
        $this->assertTrue($cache->exists('var'));
    }

    public function test_get()
    {
        $cache = Di::getDefault()->get('ManaPHP\Cache\Adapter\Db');

        $cache->delete('var');

        $this->assertFalse($cache->get('var'));
        $cache->set('var', 'value', 100);
        $this->assertSame('value', $cache->get('var'));
    }

    public function test_set()
    {
        $cache = Di::getDefault()->get('ManaPHP\Cache\Adapter\Db');

        $cache->set('var', '', 100);
        $this->assertSame('', $cache->get('var'));

        $cache->set('var', 'value', 100);
        $this->assertSame('value', $cache->get('var'));

        $cache->set('var', '{}', 100);
        $this->assertSame('{}', $cache->get('var'));

        // ttl
        $cache->set('var', 'value', 1);
        $this->assertTrue($cache->exists('var'));
        sleep(2);
        $this->assertFalse($cache->exists('var'));
    }

    public function test_delete()
    {
        $cache = Di::getDefault()->get('ManaPHP\Cache\Adapter\Db');

        //exists and delete
        $cache->set('var', 'value', 100);
        $cache->delete('var');

        // missing and delete
        $cache->delete('var');
    }
}