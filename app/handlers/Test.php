<?php
namespace app\handlers;

use app\providers\MongoDBProvider;

class Test{
	
	public function __construct($app)
	{

	}
	
	public function index($app){
		//return $app->input();

/* 		foreach($cursor as $row){
			print_r($row);
		} */
	}
	
	public function say($app, $p1=1, $p2, $p3){
		return sprintf('p1:%s p2:%s p3:%s', $p1, $p2, $p3);
	}
}