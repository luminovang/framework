<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

 namespace Luminova\Notifications\Firebase;

use \Kreait\Firebase\Factory;
use \Kreait\Firebase\Contract\Database as RealtimeDatabase;

class Database 
{
	/**
	 * @var RealtimeDatabase|null $database
	*/
	protected ?RealtimeDatabase $database = null;

	/**
	 * @var string $tableName
	*/
	protected string $tableName;
	
	/**
	 * @param string $projectId
	 * @param string $databaseUri
	*/
	public function __construct(string $projectId, string $databaseUri)
	{
		$factory = (new Factory())->withProjectId($projectId)->withDatabaseUri($databaseUri);
		$this->database = $factory->createDatabase();
	}

    public function setTable(string $table): self
	{
        $this->tableName = $table;
        return  $this;
    }

    public function getTable(): string
	{
        return $this->tableName;
    }

    public function get(string $child): mixed 
	{
		$table = $this->database->getReference($this->getTable());
		if($table->getSnapshot()->hasChild($child)){
			return $table->getChild($child)->getValue();
		}

		return null;
	}

    public function insert(string $rowId, array $data): mixed 
	{
        $table = $this->database->getReference($this->getTable());
		return $table->getChild($rowId)->set($data);
	}

    public function update(string $rowId, array $data): mixed 
	{
        $table = $this->database->getReference($this->getTable());
		return $table->getChild($rowId)->set($data);
	}
	 
	public function delete(string $rowId, string $columnId = '')
	{
		$table = $this->database->getReference($this->getTable())->getSnapshot();
		if($table->hasChild($rowId)){
			if(!empty($columnId)){
				return $table->getChild($rowId)->getChild($columnId)->remove();
			}

			return $table->getChild($rowId)->remove();
		}

		return false;
	}
}