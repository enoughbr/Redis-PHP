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
    
    function testSerializateSetGet() {
        $this->a->flush();
        
        $this->a->sset( 'foo' , array(1,2,3) );
        $data = $this->a->sget('foo');
        
        $this->assertTrue( count( array_diff(array(1,2,3), $data)) == 0 );
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
        $this->a->select(0);
    }
    
    function testPaternKey() {
        $this->a->flush();
        
        $this->a->set('foo',1);
        $this->a->set('foa',2);
        $this->a->set('integer4',3);
        
        $keys = $this->a->keys('fo[ao]');
        $this->assertTrue( count( array_diff( array('foo','foa'), $keys) ) == 0);
        
        $keys = $this->a->keys('fo[osd]');
        $this->assertTrue( count( array_diff( array('foo'), $keys) ) == 0);
        $this->a->flush();
    }
    
    function testSmget() {
        $this->a->sset('s1', 'data');
        $this->a->sset('s2', 'data1');
        $this->a->sset('s3', 'data2');
        
        $data = $this->a->smget(array('s1','s2','s3','s4'));
        
        $this->assertEquals( $data['s1'] , 'data');
        $this->assertEquals( $data['s2'] , 'data1');
        $this->assertEquals( $data['s3'] , 'data2');
        $this->assertEquals( $data['s4'] , false);
        
        $this->a->flush();
        
        $this->a->set('s1', 'data');
        $this->a->set('s2', 'data1');
        $this->a->set('s3', 'data2');
        
        $data = $this->a->mget(array('s1','s2','s3','s4'));
        
        $this->assertEquals( $data['s1'] , 'data');
        $this->assertEquals( $data['s2'] , 'data1');
        $this->assertEquals( $data['s3'] , 'data2');
        $this->assertEquals( $data['s4'] , false);
        
        $this->a->flush();
    }
    
    function testGetSet() {
        $this->a->sset('s1','data');
        $old_data = $this->a->sgetset('s1','newdata');
        $this->assertEquals( $old_data , 'data');
        
        $this->a->del('s1');
        
        $this->a->set('s1','olddata');
        $old_data = $this->a->getset('s1','new_data');
        $this->assertEquals( $old_data , 'olddata');
    }
    
    
    function testIncAppStr() {
        $this->a->set('weather','connect to system');
        $resp = $this->a->sub_string('weather',0,10);
        $this->assertEquals( $resp, 'connect to ');
        $this->a->flush_all();
        
        $this->a->set('inc',10);
        $this->assertEquals( $this->a->incr('inc') , 11 );
        $this->assertEquals( $this->a->incr('inc',2) , 13 );
        
        $this->assertEquals( $this->a->decr('inc') , 12 );
        $this->assertEquals( $this->a->decr('inc',2) , 10 );
        
        $this->a->flush();
    }
    
    
    function testSave() {
        $this->a->flush();
        
        $this->a->save();
        $this->assertEquals( $this->a->lastsave(), date('U') );
    }
    
    
}
