<?php

namespace codesaur\Logger;

use PDO;
use Exception;

use Psr\Log\LogLevel;
use Psr\Log\AbstractLogger;

use codesaur\DataObject\Column;

class Logger extends AbstractLogger
{
    use \codesaur\DataObject\TableTrait;
    
    /**
     * The log creator identification.
     *
     * @var int|null
     */
    protected $createdBy;

    /**
     * ID of the last inserted log.
     *
     * @var int|false
     */
    protected $lastInsertId;
    
    function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        
        $this->columns = array(
            'id' => (new Column('id', 'bigint', 20))->auto()->primary()->unique()->notNull(),
            'level' => new Column('level', 'varchar', 16, LogLevel::NOTICE),
            'message' => (new Column('message', 'text'))->notNull(),
            'context' => (new Column('context', 'text'))->notNull(),
            'created_at' => new Column('created_at', 'datetime'),
            'created_by' => new Column('created_by', 'bigint', 20)
        );
    }
    
    public function setTable(string $name, $collate = null)
    {
        $table = preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        if (empty($table)) {
            throw new Exception(__CLASS__ . ': Logger table name must be provided', 1103);
        }
        
        $this->name = $table . '_log';
        if ($this->hasTable($this->name)) {
            return;
        }
        
        $this->createTable($this->name, $this->getColumns(), $collate);        
        $this->__initial();
    }
    
    public function setColumns(array $columns)
    {
        // prevents from changing column infos
    }
    
    public function prepareCreatedBy(int $createdBy)
    {
        $this->createdBy = $createdBy;
    }
    
    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = array())
    {
        if (empty($this->name)) {
            return;
        }
        
        $this->lastInsertId = false;
        
        $record = array(
            'level' => $level,
            'message' => $message,
            'context' => json_encode($context),
            'created_at' => date('Y-m-d H:i:s')
        );        
        if (isset($this->createdBy)) {
            $record['created_by'] = $this->createdBy;
            $this->createdBy = null;
        }
        
        $column = $param = array();
        foreach (array_keys($record) as $key) {
            $column[] = $key;
            $param[] = ":$key";
        }
        $columns = implode(', ', $column);
        $params = implode(', ', $param);
        
        $insert = $this->prepare("INSERT INTO $this->name($columns) VALUES($params)");
        foreach ($record as $name => $value) {
            $insert->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
        }
        
        if ($insert->execute()) {
            $this->lastInsertId = (int)$this->pdo->lastInsertId();
        }
    }
    
    public function lastInsertId()
    {
        return $this->lastInsertId;
    }
    
    function interpolate($message, array $context = array())
    {
        $replace = array();
        foreach ($context as $key => $val) {
            if (!is_array($val)) {
                $replace['{{ ' . $key . ' }}'] = $val;
            }
        }
        return strtr($message, $replace);
    }
    
    public function getLogs(array $condition = []): array
    {
        $rows = array();
        if (empty($condition)) {
            $condition['ORDER BY'] = 'id Desc';
        }
        $stmt = $this->selectFrom($this->getName(), '*', $condition);
        while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $record['id'] = (int)$record['id'];
            if (!empty($record['created_by'])) {
                $record['created_by'] = (int)$record['created_by'];
            }
            $record['context'] = json_decode($record['context'], true);
            $record['message'] = $this->interpolate($record['message'], $record['context'] ?? array());
            $rows[$record['id']] = $record;
        }        
        return $rows;
    }
    
    public function getLogById($id)
    {
        $condition = array(
            'WHERE' => 'id=:id',
            'LIMIT' => 1,
            'PARAM' => array(':id' => $id)
        );        
        $stmt = $this->selectFrom($this->getName(), '*', $condition);        
        if ($stmt->rowCount() != 1) {
            return null;
        }
        
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        $record['id'] = (int)$record['id'];
        if (!empty($record['created_by'])) {
            $record['created_by'] = (int)$record['created_by'];
        }
        $record['context'] = json_decode($record['context'], true);
        $record['message'] = $this->interpolate($record['message'], $record['context'] ?? array());
        return $record;
    }
}
