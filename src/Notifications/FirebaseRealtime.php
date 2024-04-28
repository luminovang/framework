<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

namespace Luminova\Notifications;
use \Kreait\Firebase\Factory;

class FirebaseRealtime 
{
	protected $database;
	protected $tableName;
	
	public function __construct($projectId, $databaseUri){
		$factory = (new Factory())->withProjectId($projectId)->withDatabaseUri($databaseUri);
		$this->database = $factory->createDatabase();
	}

    public function setTable(string $table): self{
        $this->tableName = $table;
        return  $this;
    }

    public function getTable(): string {
        return  $this->tableName;
    }

    public function get(string $child): mixed {
		$table = $this->database->getReference($this->getTable());
		if($table->getSnapshot()->hasChild($child)){
			return $table->getChild($child)->getValue();
		}
		return null;
	}

    /*public function insert($rowId, $columnId, array $data): array {
        $table = $this->database->getReference()->getChild($this->getTable());
		return $table->getChild($rowId)->getChild($columnId)->set($data);
	}

    public function update($rowId, $columnId, array $data): array {
        $table = $this->database->getReference()->getChild($this->getTable());
		return $table->getChild($rowId)->getChild($columnId)->set($data);
	}*/

    public function insert($rowId, array $data): mixed 
	{
        $table = $this->database->getReference($this->getTable());
		return $table->getChild($rowId)->set($data);
	}

    public function update($rowId, array $data): mixed 
	{
        $table = $this->database->getReference($this->getTable());
		return $table->getChild($rowId)->set($data);
	}

	 
	public function delete($rowId, string $columnId = ''){
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