#!/usr/bin/env php
<?php
/** Cron entry point. Run from the Zabbix frontend account, never as root. */
require_once dirname(__DIR__).'/lib/IpStorage.php';require_once dirname(__DIR__).'/lib/IpScanner.php';require_once dirname(__DIR__).'/lib/TaskManager.php';
use Modules\ZabbixIpam\Lib\{IpStorage,IpScanner,TaskManager};
$storage=new IpStorage();$manager=new TaskManager($storage);$taskId=null;foreach($argv as $arg)if(strpos($arg,'--task=')===0)$taskId=substr($arg,7);
if($taskId!==null){if(!preg_match('/^[a-f0-9]{24}$/',$taskId)){fwrite(STDERR,"Invalid task ID\n");exit(2);}$lock=fopen($storage->root().'/scan_tasks/'.$taskId.'.worker.lock','c+');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){fwrite(STDERR,"Task worker already running\n");exit(0);}$task=$storage->task($taskId);if(!$task||($task['status']??'')!=='pending'){flock($lock,LOCK_UN);fclose($lock);exit(0);}(new IpScanner($storage))->run($taskId);flock($lock,LOCK_UN);fclose($lock);exit(0);}
foreach($manager->due() as $range){try{$task=$manager->start($range['id']);if(($task['status']??'')==='pending'&&empty($task['started_at'])&&empty($task['dispatched_at']))(new IpScanner($storage))->run($task['id']);}catch(\Throwable $e){fwrite(STDERR,'IPAM: '.$e->getMessage()."\n");}}
