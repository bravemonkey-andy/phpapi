<?php

namespace app\providers;

use \MongoDB\Driver\WriteConcern;
use \MongoDB\Driver\Exception\BulkWriteException;

class MongoDBProvider{
	
	private $manager;
	private $dbname;
	private $table;
	private $selects 	= array();
	private $wheres 	= array();
	private $sorts 		= array();
	private $limit 		= 999999;
	private $skip  		= 0;
	private $timeout 	= 1000;//单位为ms,该选项指定一个时间限制,以防止写操作无限制被阻塞导致无法应答给客户端
	private $journal 	= 0; //该选项要求确认写操作已经写入journal日志之后应答客户端(需要开启journal功能)

	/**
	 * 
	 * @param string $host
	 * @param int $port
	 * @param string $database
	 * @param array $options | username,password etd
	 */
	public function __construct(string $host, int $port, string $database, array $options = [])
	{
		try {
			if (is_null($this->manager)) {
				$this->dbname = $database;
				$link = sprintf('mongodb://%s:%d/%s', $host, $port, $database);
				$this->manager = new \MongoDB\Driver\Manager($link, $options);
			}
		} catch (\Exception $e) {
			$this->_handleException($e);
		}
	}
	
	public function raw(\Closure $callback)
	{
		return $callback($this->manager);
	}

	public function table(string $table){
		$this->table = $table;
		
		return $this;
	}	
	
	/**
	 * 
	 * @param array $columns
	 * @return \MongoDB\Driver\Cursor
	 */
	public function get(array $columns = ['*'])
	{
		$this->select($columns);
		$options = [
			'projection' => $this->selects,
			'sort' 		 => $this->sorts,
			'skip' 		 => $this->skip,
			'limit' 	 => $this->limit,
			'slaveOk' 	 => true,
		];
		$query = new \MongoDB\Driver\Query($this->wheres, $options);
		return $this->manager->executeQuery($this->getNamespace(), $query);
	}

	/**
	 * 
	 * @param array $columns
	 * @return stdClass
	 */
	public function find(array $columns = ['*'])
	{
		$this->select($columns);
		$options = [
			'projection' => $this->selects,
			'sort' 		 => $this->sorts,
			'skip' 		 => 0,
			'limit' 	 => 1,
			'slaveOk' 	 => true,
		];
		$query = new \MongoDB\Driver\Query($this->wheres, $options);
		$cursor = $this->manager->executeQuery($this->getNamespace(), $query);
		foreach($cursor as $v) return $v;
		return new \stdClass();
	}

	public function select(array $columns = ['*'])
	{
		if (count($columns) > 0) {
			foreach($columns as $v) $this->selects[$v] = 1;
		}
		
		return $this;
	}
	
	public function insert(array $columns = [])
	{
		$result = $bulk->batchInsert([$columns]);
		
		return $result->getInsertedCount();
	}

	
	public function batchInsert(array $columns = [])
	{
		$bulk = new \MongoDB\Driver\BulkWrite;
		
		foreach ($columns as $column) {
			$bulk->insert($column);
		}
		
		$result = $this->manager->executeBulkWrite($this->getNamespace(), $bulk);
		
		return $result->getInsertedCount();
	}
	
	public function update(array $columns, array $wheres = [])
	{
		$result = $this->_update(['$set' => $columns], $wheres, true, false);	
		
		return $result->getModifiedCount();
	}

	public function replace(array $columns, array $wheres = [])
	{
		$result = $this->_update(['$set' => $columns], $wheres, true, true);
		
		return $result->getUpsertedCount();
	}
	
	private function _update(array $command, array $wheres, boolean $multi, boolean $upsert)
	{
		try {
			$bulk = new \MongoDB\Driver\BulkWrite;
			$bulk->update(
				array_merge($this->wheres, $wheres), 
				$command, 
				['multi' => $multi, 'upsert' => $upsert]
			);	
					
			return $this->manager->executeBulkWrite($this->getNamespace(), $bulk);
		} catch (BulkWriteException $e) {
			
		}
	}
		
	public function delete(array $wheres = [])
	{
		$bulk = new \MongoDB\Driver\BulkWrite;
		
		$bulk->delete(
			array_merge($this->wheres, $wheres),
			['limit' => false]
		);
		$result = $this->manager->executeBulkWrite($this->getNS(), $bulk);
		
		return $result->getDeletedCount();		
	}
	
	public function increment(string $column, int $amount = 1, array $where = []) 
	{
		return $this->_update(['$inc' => [$column => $amount]], $wheres, true, false);
	}
	
	public function decrement(string $column, int $amount = 1, array $where = [])
	{
		return $this->_update(['$inc' => [$column => -abs($amount)]], $wheres, true, false);
	}

	public function groupBy(array $columns){
		
	}
	
	public function aggregate(array $wheres = [])
	{	
		$command = new \MongoDB\Driver\Command([
				'aggregate' => $this->table,
				'pipeline' => [],
				'cursor' => new stdClass,
		]);
		return $this->manager->executeCommand($this->dbname, $command);
	}
	
	public function orderBy(string $column, string $direction = 'asc')
	{
		$this->sorts[$column] = ($direction == 'asc' ? 1 : -1);
		
		return $this;
	}
	
	public function skip(int $value)
	{
		$this->skip = $value;
		
		return $this;
	}
	
	public function limit(int $value)
	{
		$this->limit = $value;
		
		return $this;
	}
	
	public function forPage(int $page, int $perPage = 15) 
	{
		$this->skip  = ($page - 1) * $perPage;
		$this->limit = $perPage;
		
		return $this;
	}	

	public function getNamespace()
	{
		return $this->dbname.'.'.$this->table;
	}

	private function _handleException(\Exception $e){
		echo $e->getMessage();
	}	
}