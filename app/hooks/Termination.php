<?php
namespace app\hooks;

class Termination{
	
	
	public function handle($app, ...$uri_params){
		//echo "Termination handle";
		//print_r($uri_params);
	}
	
	public function terminate($app, $output) {
		//echo "<br/>Termination terminate";
		//var_dump($output);
	}
}