<?php

namespace codesaur\Logger;

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
    protected ?int $createdBy = null;

    /**
     * ID of the last inserted log.
     *
     * @var int|false
     */
    protected int|false $lastInsertId = false;
    
    function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        
        $this->columns = [
            'id' => (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
            'level' => new Column('level', 'varchar', 16, LogLevel::NOTICE),
            'message' => (new Column('message', 'text'))->notNull(),
            'context' => (new Column('context', 'text'))->notNull(),
            'created_at' => new Column('created_at', 'datetime'),
            'created_by' => new Column('created_by', 'bigint', 8)
        ];
    }
    
    public function setTable(string $name, ?string $collate = null)
    {
        $table = preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        if (empty($table)) {
            throw new \Exception(__CLASS__ . ': Logger table name must be provided', 1103);
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
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->lastInsertId = false;
        
        if (empty($this->name)) {
            return;
        }
        
        $record = [
            'level' => (string) $level,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
            'context' => json_encode($context) ?: ('{"log-context-write-error":"' . json_last_error_msg() . '"}')
        ];        
        if (isset($this->createdBy)) {
            $record['created_by'] = $this->createdBy;
            $this->createdBy = null;
        }
        
        $column = $param = [];
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
            $id = $this->pdo->lastInsertId();
            if (is_int($id)) {
                $this->lastInsertId = (int) $id;
            }
        }
    }
    
    public function lastInsertId(string $name = null): string|false
    {
        return $this->lastInsertId;
    }
    
    function interpolate(string $message, array $context = []): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val)) {
                $replace['{{ ' . $key . ' }}'] = $val;
            }
        }
        return strtr($message, $replace);
    }
    
    public function getLogs(array $condition = []): array
    {
        $rows = [];
        if (empty($condition)) {
            $condition['ORDER BY'] = 'id Desc';
        }
        $stmt = $this->selectFrom($this->getName(), '*', $condition);
        while ($record = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $record['id'] = (int) $record['id'];
            if (!empty($record['created_by'])) {
                $record['created_by'] = (int) $record['created_by'];
            }
            $record['context'] = json_decode($record['context'], true) ?? ['log-context-read-error' => json_last_error_msg()];
            $record['message'] = $this->interpolate($record['message'], $record['context'] ?? []);
            $rows[$record['id']] = $record;
        }        
        return $rows;
    }
    
    public function getLogById(int $id): array|null
    {
        $condition = [
            'LIMIT' => 1,
            'WHERE' => 'id=:id',
            'PARAM' => [':id' => $id]
        ];
        $stmt = $this->selectFrom($this->getName(), '*', $condition);        
        if ($stmt->rowCount() != 1) {
            return null;
        }
        
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);
        $record['id'] = (int) $record['id'];
        if (!empty($record['created_by'])) {
            $record['created_by'] = (int) $record['created_by'];
        }
        $record['context'] = json_decode($record['context'], true) ?? ['log-context-read-error' => json_last_error_msg()];
        $record['message'] = $this->interpolate($record['message'], $record['context'] ?? []);
        return $record;
    }
}
