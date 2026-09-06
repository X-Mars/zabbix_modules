<?php
namespace Modules\ZabbixIpam\Lib;
class TaskManager {
    private $storage;
    public function __construct(?IpStorage $storage=null){$this->storage=$storage?:new IpStorage();}
    public function start(string $rangeId,int $shardSize=64): array { $range=$this->storage->range($rangeId);if(!$range)throw new \InvalidArgumentException('Range not found');foreach($this->storage->tasks() as $old)if(($old['range_id']??'')===$rangeId&&in_array($old['status']??'',['pending','running'],true))return $old;$parsed=IpScanner::parseRange($range['range']);$id=bin2hex(random_bytes(12));$task=['id'=>$id,'range_id'=>$rangeId,'status'=>'pending','created_at'=>gmdate('c'),'updated_at'=>gmdate('c'),'total'=>$parsed['count'],'scanned'=>0,'alive'=>0,'ports'=>IpScanner::ports($range['ports']??''),'shards'=>IpScanner::shards($parsed,$shardSize),'completed_shards'=>0,'current_shard'=>0,'estimated_remaining'=>null];$this->storage->saveTask($task);$range['scan_status']='pending';$this->storage->saveRange($range);return $task; }
    public function dispatch(string $id): bool {
        $cli=dirname(__DIR__).'/cli/scan_cron.php';
        if(!is_file($cli)||!preg_match('/^[a-f0-9]{24}$/',$id))return false;
        $task=$this->storage->task($id);if(!$task||in_array($task['status']??'',['completed','failed','stopped'],true))return false;
        if(($task['status']??'')==='running'||!empty($task['dispatched_at']))return true;
        $php=$this->findCli();
        if($php===null){$this->fail($id,'PHP CLI is not installed. Install php-cli for background scans and cron.');return false;}
        $task['dispatched_at']=gmdate('c');$task['updated_at']=$task['dispatched_at'];$this->storage->saveTask($task);
        $log=$this->storage->root().'/scan_tasks/'.$id.'.log';
        $cmd='nohup '.escapeshellarg($php).' '.escapeshellarg($cli).' --task='.escapeshellarg($id).' >> '.escapeshellarg($log).' 2>&1 &';
        @exec($cmd,$unused,$code);
        if($code!==0){$this->fail($id,'Unable to launch scan worker (exit '.$code.').');return false;}
        return true;
    }
    private function findCli():?string{foreach(['/usr/bin/php','/usr/local/bin/php','/opt/remi/php83/root/usr/bin/php'] as $p)if(is_executable($p))return $p;if(PHP_SAPI==='cli'&&is_executable(PHP_BINARY)&&strpos(basename(PHP_BINARY),'fpm')===false)return PHP_BINARY;return null;}
    private function fail(string $id,string $message):void{$t=$this->storage->task($id);if(!$t)return;$t['status']='failed';$t['error']=$message;$t['updated_at']=gmdate('c');$this->storage->saveTask($t);}
    public function stop(string $id):void{$t=$this->storage->task($id);if(!$t)throw new \InvalidArgumentException('Task not found');if(in_array($t['status'],['pending','running'],true)){$t['status']='stopped';$t['updated_at']=gmdate('c');$this->storage->saveTask($t);}}
    /** Every cron invocation schedules all enabled ranges; the crontab expression defines the interval. */
    public function due():array{return array_values(array_filter($this->storage->ranges(),fn($r)=>!empty($r['enabled'])));}
}
