<?php
/**
 * Copyright (c) 2012, ideawu
 * All rights reserved.
 * @author: ideawu
 * @link: http://www.ideawu.com/
 *
 * unit test.
 */

include(dirname(__FILE__) . '/../api/php/SSDB.php');

class SSDBTest extends UnitTest{
	private $ssdb;

	function __construct(){
		$host = '127.0.0.1';
		$port = 8888;
		$this->ssdb = new SimpleSSDB($host, $port);
		$this->clear();
	}

	function clear(){
		$ssdb = $this->ssdb;
		$deleted = 0;
		while(1){
			$ret = $ssdb->scan('TEST_', 'TEST_'.pack('C', 255), 1000);
			if(!$ret){
				break;
			}
			foreach($ret as $k=>$v){
				$ssdb->del($k);
				$deleted += 1;
			}
		}
		while(1){
			$names = $ssdb->hlist('TEST_', 'TEST_'.pack('C', 255), 1000);
			if(!$names){
				break;
			}
			foreach($names as $name){
				$ret = $ssdb->hscan($name, '', '', 1000);
				foreach($ret as $k=>$v){
					$ssdb->hdel($name, $k);
					$deleted += 1;
				}
			}
		}
		while(1){
			$names = $ssdb->zlist('TEST_', 'TEST_'.pack('C', 255), 1000);
			if(!$names){
				break;
			}
			foreach($names as $name){
				$ret = $ssdb->zscan($name, '', '', '', 1000);
				foreach($ret as $k=>$v){
					$ssdb->zdel($name, $k);
					$deleted += 1;
				}
			}
		}
		if($deleted > 0){
			echo "clear $deleted\n";
		}
	}

	function test_kv(){
		$ssdb = $this->ssdb;
		$val = str_repeat(mt_rand(), mt_rand(1, 100));

		$ssdb->set('TEST_a', $val);
		$ssdb->set('TEST_b', $val);

		$ret = $this->ssdb->get('TEST_a');
		$this->assert($ret === $val);

		$ret = $ssdb->scan('TEST_', 'TEST_'.pack('C', 255), 10);
		$this->assert(count($ret) == 2);
		$ret = $ssdb->scan('TEST_a', 'TEST_'.pack('C', 255), 10);
		$this->assert(count($ret) == 1);
		$ret = $ssdb->scan('TEST_b', 'TEST_'.pack('C', 255), 10);
		$this->assert(count($ret) == 0);
		$ret = $ssdb->scan('TEST_', 'TEST_a', 10);
		$this->assert(count($ret) == 1);
		$ret = $ssdb->scan('TEST_', 'TEST_b', 10);
		$this->assert(count($ret) == 2);

		$ret = $ssdb->rscan('TEST_'.pack('C', 255), 'TEST_', 10);
		$this->assert(count($ret) == 2);
		$ret = $ssdb->rscan('TEST_b', 'TEST_'.pack('C', 0), 10);
		$this->assert(count($ret) == 1);
		$ret = $ssdb->rscan('TEST_a', 'TEST_'.pack('C', 0), 10);
		$this->assert(count($ret) == 0);
		$ret = $ssdb->rscan('TEST_'.pack('C', 255), 'TEST_a', 10);
		$this->assert(count($ret) == 2);
		$ret = $ssdb->rscan('TEST_'.pack('C', 255), 'TEST_b', 10);
		$this->assert(count($ret) == 1);

		$ret = $ssdb->keys('TEST_', 'TEST_'.pack('C', 255), 10);
		$this->assert(count($ret) == 2);

		$ssdb->del('TEST_a');
		$ret = $ssdb->get('TEST_a');
		$this->assert($ret === null);
		$ssdb->del('TEST_b');
	}

	function test_hash(){
		$ssdb = $this->ssdb;
		$name = "TEST_" . str_repeat(mt_rand(), mt_rand(1, 6));
		$key = "TEST_" . str_repeat(mt_rand(), mt_rand(1, 6));
		$val = str_repeat(mt_rand(), mt_rand(1, 30));

		$ret = $ssdb->hsize($name);
		$this->assert($ret === 0);

		$ret = $ssdb->hset($name, $key, $val);
		$ret = $ssdb->hget($name, $key);
		$this->assert($ret === $val);

		$ret = $ssdb->hsize($name);
		$this->assert($ret === 1);
		$ret = $ssdb->hscan($name, '', '', 10);
		$this->assert(count($ret) == 1);
		$ret = $ssdb->hrscan($name, '', '', 10);
		$this->assert(count($ret) == 1);
		$ret = $ssdb->hkeys($name, '', '', 10);
		$this->assert(count($ret) == 1);

		$ret = $ssdb->hdel($name, $key);
		$ret = $ssdb->hsize($name);
		$this->assert($ret === 0);
		$ret = $ssdb->hscan($name, '', '', 10);
		$this->assert(count($ret) == 0);
		$ret = $ssdb->hrscan($name, '', '', 10);
		$this->assert(count($ret) == 0);
		$ret = $ssdb->hkeys($name, '', '', 10);
		$this->assert(count($ret) == 0);

		$ret = $ssdb->hset($name, 'a', $val);
		$ret = $ssdb->hset($name, 'b', $val);
		$ret = $ssdb->hscan($name, '', '', 10);
		$this->assert(count($ret) == 2);
		foreach($ret as $k=>$v){
			$this->assert($v === $val);
		}
		$ret = $ssdb->hscan($name, '', 'a', 10);
		$this->assert(count($ret) == 1);
		$ret = $ssdb->hscan($name, '', 'b', 10);
		$this->assert(count($ret) == 2);
		$ret = $ssdb->hrscan($name, '', 'b', 10);
		$this->assert(count($ret) == 1);
		$ret = $ssdb->hrscan($name, '', 'a', 10);
		$this->assert(count($ret) == 2);

		$ret = $ssdb->hscan($name, 'a', '', 10);
		$this->assert(count($ret) == 1);
		$ret = $ssdb->hscan($name, 'b', '', 10);
		$this->assert(count($ret) == 0);
		$ret = $ssdb->hrscan($name, '', '', 10);
		$this->assert(count($ret) == 2);
		$ret = $ssdb->hrscan($name, 'b', '', 10);
		$this->assert(count($ret) == 1);
		$ret = $ssdb->hrscan($name, 'a', '', 10);
		$this->assert(count($ret) == 0);
		$ret = $ssdb->hkeys($name, '', '', 10);
		$this->assert(count($ret) == 2);
		$ret = $ssdb->hkeys($name, 'a', '', 10);
		$this->assert(count($ret) == 1);
		$ret = $ssdb->hkeys($name, 'b', '', 10);
		$this->assert(count($ret) == 0);
		$ret = $ssdb->hdel($name, 'a');
		$ret = $ssdb->hdel($name, 'b');

		$ssdb->hset("TEST_a", 'a', 1);
		$ssdb->hset("TEST_b", 'a', 1);
		$ssdb->hset("TEST_c", 'a', 1);
		$ret = $ssdb->hlist("TEST_a", "TEST_b", 100);
		$this->assert(count($ret) == 1);
		$this->assert($ret[0] == "TEST_b");

		$ssdb->hdel('TEST_a', 'a');
		$ret = $ssdb->hget('TEST_a', 'a');
		$this->assert($ret === null);
	}

	function test_zset(){
		$ssdb = $this->ssdb;
		$name = "TEST_" . str_repeat(mt_rand(), mt_rand(1, 6));
		$key = "TEST_" . str_repeat(mt_rand(), mt_rand(1, 6));
		$val = mt_rand();

		$ret = $ssdb->zsize($name);
		$this->assert($ret === 0);

		$ret = $ssdb->zset($name, $key, $val);
		$ret = $ssdb->zget($name, $key);
		$this->assert($ret === $val);

		$ret = $ssdb->zsize($name);
		$this->assert($ret === 1);
		$ret = $ssdb->zscan($name, '', '', '', 10);
		$this->assert(count($ret) == 1);
		$ret = $ssdb->zrscan($name, '', '', '', 10);
		$this->assert(count($ret) == 1);
		$ret = $ssdb->zkeys($name, '', '', '', 10);
		$this->assert(count($ret) == 1);

		$ret = $ssdb->zdel($name, $key);
		$ret = $ssdb->zsize($name);
		$this->assert($ret === 0);
		$ret = $ssdb->zscan($name, '', '', '', 10);
		$this->assert(count($ret) == 0);
		$ret = $ssdb->zrscan($name, '', '', '', 10);
		$this->assert(count($ret) == 0);
		$ret = $ssdb->zkeys($name, '', '', '', 10);
		$this->assert(count($ret) == 0);

		$ret = $ssdb->zset($name, 'a', $val);
		$ret = $ssdb->zset($name, 'b', $val);

		$ret = $ssdb->zrank($name, 'a');
		$this->assert($ret != -1);
		$ret = $ssdb->zrrank($name, 'a');
		$this->assert($ret != -1);

		$ret = $ssdb->zrange($name, 0, 10);
		$this->assert(count($ret) == 2);
		$ret = $ssdb->zrrange($name, 0, 10);
		$this->assert(count($ret) == 2);

		$ret = $ssdb->zscan($name, '', '', '', 10);
		$this->assert(count($ret) == 2);
		foreach($ret as $k=>$v){
			$this->assert($v == $val);
		}
		$ret = $ssdb->zscan($name, 'a', '', '', 10);
		$this->assert(count($ret) == 1);
		$ret = $ssdb->zscan($name, 'b', '', '', 10);
		$this->assert(count($ret) == 0);
		$ret = $ssdb->zrscan($name, '', '', '', 10);
		$this->assert(count($ret) == 2);
		$ret = $ssdb->zrscan($name, 'b', $val, '', 10);
		$this->assert(count($ret) == 1);
		$ret = $ssdb->zrscan($name, 'a', $val, '', 10);
		$this->assert(count($ret) == 0);
		$ret = $ssdb->zkeys($name, '', '', '', 10);
		$this->assert(count($ret) == 2);
		$ret = $ssdb->zkeys($name, 'a', $val, '', 10);
		$this->assert(count($ret) == 1);
		$ret = $ssdb->zkeys($name, 'b', $val, '', 10);
		$this->assert(count($ret) == 0);
		$ret = $ssdb->zdel($name, 'a');
		$ret = $ssdb->zdel($name, 'b');

		$ssdb->zset("TEST_a", 'a', 1);
		$ssdb->zset("TEST_b", 'a', 1);
		$ssdb->zset("TEST_c", 'a', 1);
		$ret = $ssdb->zlist("TEST_a", "TEST_b", 100);
		$this->assert(count($ret) == 1);
		$this->assert($ret[0] == "TEST_b");

		$ssdb->zdel('TEST_a', 'a');
		$ret = $ssdb->zget('TEST_a', 'a');
		$this->assert($ret === null);
	}
}

class UnitTest{
	private $result = array(
			'passed' => 0,
			'failed' => 0,
			'tests' => array(
				),
			);

	function run(){
		$class_name = get_class($this);
		$methods = get_class_methods($class_name);
		foreach($methods as $method){
			if(strpos($method, 'test_') === 0){
				$this->$method();
			}
		}
		$this->report();
		$this->clear();
	}

	function report(){
		$res = $this->result;
		foreach($res['tests'] as $test){
			if($test[0] === false){
				var_dump($test);
			}
		}
		printf("passed: %3d, failed: %3d\n", $res['passed'], $res['failed']);
	}

	function assert($val, $desc=''){
		if($val === true){
			$this->result['passed'] ++;
		}else{
			$this->result['failed'] ++;
		}
		$bt = debug_backtrace(false);
		$func = $bt[1]['function'];
		$file = basename($bt[1]['file']);
		$line = $bt[0]['line'];
		$this->result['tests'][] = array(
				$val, $func, $file, $line, $desc
				);
	}

}


$test = new SSDBTest();
$test->run();

