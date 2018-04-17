<?php
/**
 * 类自动加载器 
 * @author sunwei
 *
 */
class ClassLoader{
	
	public static $loadedClasses = [];
	
	public static function loadClass(string $classpath)
	{
		$pos = strrpos($classpath, '\\');

		$namespace = substr($classpath, 0, $pos);
		$classname = substr($classpath, $pos+1);
		
		if ( ! isset(self::$loadedClasses[$classpath]) ) {
			$filename = str_replace('\\', DIRECTORY_SEPARATOR, $classpath).'.php';
		} 
		else {
			$filename = str_replace('\\', DIRECTORY_SEPARATOR, self::$loadedClasses[$classpath]).'.php';
		}
		
		$filepath = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.$filename;

		if ( ! file_exists($filepath) ) {
			throw new \Exception('The class file "'.$filepath.'" is not found', 404);
		}
		
		self::$loadedClasses[$classpath] = true;

		require_once $filepath;
	}
}

spl_autoload_register(['ClassLoader', 'loadClass'], true, true);
