<?php

namespace codesaur\Logger\Example;

/* DEV: v1.2021.04.15
 * 
 * This is an example script!
 */

ini_set('display_errors', 'On');
error_reporting(\E_ALL & ~\E_STRICT & ~\E_NOTICE);

require_once '../vendor/autoload.php';

use codesaur\Logger\Logger;

try {
    $dsn = 'mysql:host=localhost;charset=utf8';
    $username = 'root';
    $passwd = '';
    $options = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];
    
    $pdo = new \PDO($dsn, $username, $passwd, $options);
    echo 'connected to mysql...<br/>';
    
    $database = 'logger_example';
    if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS $database COLLATE " . $pdo->quote('utf8_unicode_ci'));
    }

    $pdo->exec("USE $database");
    echo "starting to use database [$database]<br/>";
    
    $logger = new Logger($pdo);
    $logger->setTable('default', 'utf8_unicode_ci');
    
    $oldLogs = $logger->getLogs();
    
    $logger->notice('Started using logger', ['Client IP' => $_SERVER['REMOTE_ADDR'], 'User-Agent' => $_SERVER['HTTP_USER_AGENT']]);
    $logger->debug('Starting new session');

    echo "<hr><br/>";
    $lastId = $logger->lastInsertId();
    if ($lastId !== false) {    
        var_dump(['Last log id[' . $lastId . ']' => $logger->getLogById($lastId)]);
    }

    echo "<hr><br/>LIST OF ALL LOGS FROM PREVIOUS SESSIONS: sorted latest to oldest<br/>";
    var_dump($oldLogs);

    $logger->prepareCreatedBy(1);
    $logger->info('Listed total {{ total }} logs', ['total' => count($oldLogs)]);
} catch (\Throwable $th) {
    die('<br/>{' . date('Y-m-d H:i:s') . '} Error[' . $th->getCode() . '] => ' . $th->getMessage());
}
