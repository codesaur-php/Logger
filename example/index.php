<?php

namespace codesaur\Logger\Example;

/* DEV: v1.2021.04.15
 * 
 * This is an example script!
 */

require_once '../vendor/autoload.php';

use PDO;
use Exception;

use codesaur\Logger\Logger;

ini_set('display_errors', 'On');
error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);

try {
    $dsn = 'mysql:host=localhost;charset=utf8';
    $username = 'root';
    $passwd = '';
    $options = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);
    
    $pdo = new PDO($dsn, $username, $passwd, $options);
    echo 'connected to mysql...<br/>';
    
    $database = 'logger_example';
    if ($_SERVER['HTTP_HOST'] === 'localhost'
            && in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1'))
    ) {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS $database COLLATE " . $pdo->quote('utf8_unicode_ci'));
    }

    $pdo->exec("USE $database");
    echo 'started using example database!<br/>';
    
    $logger = new Logger($pdo);
    $logger->setTable('default');
    
    $oldLogs = $logger->getLogs();
    
    $logger->notice('Started using logger', array('Client IP' => $_SERVER['REMOTE_ADDR'], 'User-Agent' => $_SERVER['HTTP_USER_AGENT']));
    $logger->debug('Reading log with id=1', array('id' => 1));

    echo "<hr><br/>";
    var_dump(array('Log with id=10' => $logger->getLogById(10)));

    echo "<hr><br/>LIST OF ALL LOGS FROM PREVIOUS SESSIONS: sorted latest to oldest<br/>";
    var_dump($oldLogs);

    $logger->prepareCreatedBy(1);
    $logger->info('Listed all logs');
} catch (Exception $ex) {
    die('[' . date('Y-m-d H:i:s') . ' Error] ' . $ex->getMessage());
}
