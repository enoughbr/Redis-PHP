<?php
error_reporting(E_ALL);
ini_set('display_errors','On');

require_once 'PHPUnit/Autoload.php';
require_once 'redis.php';

class RedisTest extends PHPUnit_Framework_TestCase {
    
    function __construct() {
        try {
            $this->a = new RedisPHP('127.0.0.1', 6379, 'foobared');
        } catch(RedisException $e) {
            exit("Cannot connect to Redis database\n");
        }
        
    }
    
    function testSet() {
        $this->a->set('foo','foobar');
        $this->assertEquals( $this->a->get('foo') , 'foobar' );
        
        $this->a->del('foo');
        $this->assertEquals( $this->a->get('foo') , false );
        
        $this->assertEquals( $this->a->get('no_isset_key') , false);
    }
    
    
    function testExistRename() {
        $this->a->set('data',1);
        $this->assertTrue( $this->a->exists('data') );
        $this->assertTrue( !$this->a->exists('no_isset_key') );
        
        $this->a->rename('data','new_key');
        $this->assertTrue( !$this->a->exists('data') && $this->a->exists('new_key') );
        
        $this->a->set('cinema', 1);
        $this->assertTrue( !$this->a->safely_rename('cinema','new_key') );
        $this->assertEquals( $this->a->error() , 'Key already exist');
    }
    
    function testExpire() {
        $this->a->set('exp','value');
        $this->assertTrue( $this->a->expire('exp',20000) );
        $this->assertTrue( !$this->a->expire('not_exist_key',20000) );
        $this->assertEquals( $this->a->ttl('exp') , 20000 );
    }
    
    function testDatabase() {
        $this->a->flush();
        $this->assertEquals( $this->a->size() , 0 );
        
        $this->a->set('key','val');
        $this->assertEquals( $this->a->size() , 1 );
        
        $this->assertTrue( $this->a->select(5) );
        $this->assertTrue( !$this->a->select(9999) );
        $this->assertEquals( $this->a->current_database() , 5 );
        
        $this->a->select(0);
        $this->a->move( 'key', 1);
        $this->a->select(1);
        $this->assertTrue( $this->a->exists('key') );
    }
    
}

