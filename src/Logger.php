<?php

namespace codesaur\Logger;

use Psr\Log\LogLevel;
use Psr\Log\AbstractLogger;

use codesaur\DataObject\Column;

class Logger extends AbstractLogger
{
    use \codesaur\DataObject\TableTrait;
    
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->columns = [
            'id' => (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
            'level' => new Column('level', 'varchar', 16, LogLevel::NOTICE),
            'message' => (new Column('message', 'text'))->notNull(),
            'context' => (new Column('context', 'mediumtext'))->notNull(),
            'created_at' => new Column('created_at', 'datetime'),
            'created_by' => new Column('created_by', 'bigint', 8)
        ];
    }
    
    public function setTable(string $name, ?string $collate = null)
    {
        $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $name);
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
        throw new \Exception(__CLASS__ . ": You can't change predefined columns of Logger table!");
    }

    protected function __initial()
    {
    }
    
    /**
     * {@inheritdoc}
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (empty($this->name)) {
            return;
        }
        
        $record = [
            'level' => (string) $level,
            'message' => $message,
            'created_at' => \date('Y-m-d H:i:s'),
            'context' => \json_encode($context, \JSON_INVALID_UTF8_SUBSTITUTE)
                ?: \json_encode(\mb_convert_encoding($context, 'UTF-8', 'UTF-8'))
                ?: ('{"log-context-write-error":"' . \addslashes(\json_last_error_msg()) . '"}')
        ];

        $by_account = \getenv('CODESAUR_ACCOUNT_ID', true);
        if ($by_account) {
            $record['created_by'] = $by_account;
        }
        
        $column = $param = [];
        foreach (\array_keys($record) as $key) {
            $column[] = $key;
            $param[] = ":$key";
        }
        $columns = \implode(', ', $column);
        $params = \implode(', ', $param);
        
        $insert = $this->prepare("INSERT INTO $this->name($columns) VALUES($params)");
        foreach ($record as $name => $value) {
            $insert->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
        }
        $insert->execute();
    }
    
    private function interpolate(string $message, array $context = []): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!\is_array($val)) {
                $replace['{{ ' . $key . ' }}'] = $val;
            }
        }
        return \strtr($message, $replace);
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
            $record['context'] =
                    \json_decode($record['context'], true, 100000, \JSON_INVALID_UTF8_SUBSTITUTE)
                    ?? ['log-context-read-error' => \json_last_error_msg()];
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
        $record['context'] = 
                \json_decode($record['context'], true, 100000, \JSON_INVALID_UTF8_SUBSTITUTE)
                ?? ['log-context-read-error' => \json_last_error_msg()];
        $record['message'] = $this->interpolate($record['message'], $record['context'] ?? []);
        return $record;
    }
}
