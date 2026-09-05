<?php
namespace Modules\ZabbixIpam\Lib;

/** Safe IPv4 scanner. It never interpolates addresses or ports into a shell command. */
class IpScanner {
    public const MAX_HOSTS = 65536;
    private $storage;
    public function __construct(?IpStorage $storage = null) { $this->storage = $storage ?: new IpStorage(); }
    /** Compatibility placeholder: this module performs ICMP checks only. */
    public static function ports($ports): array { return []; }
    public static function parseRange(string $value): array {
        $value=trim($value); if(strpos($value,'/')!==false){[$ip,$bits]=array_pad(explode('/',$value,2),2,null);if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)===false||filter_var($bits,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>32]])===false)throw new \InvalidArgumentException('Invalid IPv4 CIDR');$mask=$bits==0?0:(0xffffffff << (32-(int)$bits));$start=ip2long($ip)&$mask;$end=$start+(2**(32-(int)$bits))-1;} else {[$a,$b]=array_pad(preg_split('/\s*-\s*/',$value),2,null);if(filter_var($a,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)===false||filter_var($b,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)===false)throw new \InvalidArgumentException('Invalid IPv4 range');$start=ip2long($a);$end=ip2long($b);if($start>$end)throw new \InvalidArgumentException('Range start is after end');}
        $count=$end-$start+1;if($count>self::MAX_HOSTS)throw new \InvalidArgumentException('Range exceeds maximum size');return ['start'=>(int)$start,'end'=>(int)$end,'count'=>(int)$count];
    }
    public static function shards(array $range, int $size=64): array { $size=max(1,min(1024,$size));$out=[];for($s=$range['start'];$s<=$range['end'];$s+=$size)$out[]=['start'=>$s,'end'=>min($range['end'],$s+$size-1)];return $out; }
    public function run(string $taskId): void {
        $task=$this->storage->task($taskId);if(!$task)return;$task['status']='running';$task['started_at']=gmdate('c');$this->storage->saveTask($task);
        $rows=[];$started=microtime(true);
        try {
            if(function_exists('pcntl_fork')&&PHP_SAPI==='cli')$this->runParallel($taskId,$task,$rows,$started,4);
            else foreach($task['shards'] as $index=>$shard){if($this->stopped($taskId))return;$this->mergeShard($taskId,$index,$this->scanShard($shard,$task['ports']),$task,$rows,$started);}
            $task['status']='completed';$task['finished_at']=gmdate('c');$task['updated_at']=gmdate('c');$this->storage->saveResults($taskId,$rows);$this->storage->saveTask($task);$range=$this->storage->range($task['range_id']);if($range){$range['last_scan']=$task['finished_at'];$range['scan_status']='completed';$this->storage->saveRange($range);}
        } catch(\Throwable $e){$task['status']='failed';$task['error']=$e->getMessage();$task['updated_at']=gmdate('c');$this->storage->saveTask($task);}
    }
    private function runParallel(string $taskId,array &$task,array &$rows,float $started,int $workers):void{
        $active=[];
        foreach($task['shards'] as $index=>$shard){
            if($this->stopped($taskId)){if(function_exists('posix_kill'))foreach(array_keys($active) as $pid)posix_kill($pid,SIGTERM);return;}
            while(count($active)>=$workers)$this->collectWorker($taskId,$active,$task,$rows,$started);
            $pid=pcntl_fork();
            if($pid===0){$this->writeShard($taskId,$index,$shard,$task['ports']);exit(0);}
            if($pid<0)throw new \RuntimeException('pcntl_fork failed');
            $active[$pid]=$index;
        }
        while($active)$this->collectWorker($taskId,$active,$task,$rows,$started);
    }
    private function collectWorker(string $taskId,array &$active,array &$task,array &$rows,float $started):void{
        $pid=pcntl_wait($status);if($pid<=0||!isset($active[$pid]))throw new \RuntimeException('Unable to wait for scan worker.');$index=$active[$pid];unset($active[$pid]);
        if(!pcntl_wifexited($status)||pcntl_wexitstatus($status)!==0)throw new \RuntimeException('Scan worker failed for shard '.($index+1).'.');
        $part=$this->loadShard($taskId,$index);if($part===null)throw new \RuntimeException('Missing result for shard '.($index+1).'.');
        $this->mergeShard($taskId,$index,$part,$task,$rows,$started);
    }
    private function mergeShard(string $taskId,int $index,array $part,array &$task,array &$rows,float $started):void{
        foreach($part as $row){$rows[]=$row;$task['scanned']++;if($row['alive'])$task['alive']++;}$task['completed_shards']++;$task['current_shard']=$index+1;$task['updated_at']=gmdate('c');$task['estimated_remaining']=max(0,(microtime(true)-$started)/max(1,$task['scanned'])*($task['total']-$task['scanned']));$this->storage->saveResults($taskId,$rows);$this->storage->saveTask($task);
    }
    private function stopped(string $taskId):bool{$fresh=$this->storage->task($taskId);return($fresh['status']??'')==='stopped';}
    private function scanShard(array $shard,array $ports): array { $rows=[];for($n=$shard['start'];$n<=$shard['end'];$n++)$rows[]=$this->probe(long2ip($n),$ports);return $rows; }
    private function shardFile(string $task,int $index):string{return $this->storage->root().'/results/'.$task.'.shard-'.$index.'.json';}
    private function writeShard(string $task,int $index,array $shard,array $ports):void{file_put_contents($this->shardFile($task,$index),json_encode($this->scanShard($shard,$ports)),LOCK_EX);}
    private function loadShard(string $task,int $index):?array{$f=$this->shardFile($task,$index);if(!is_file($f))return null;$data=json_decode((string)@file_get_contents($f),true);@unlink($f);return is_array($data)?$data:null;}
    private function probe(string $ip,array $ports): array { $then=microtime(true);$alive=$this->ping($ip);
        return ['ip'=>$ip,'alive'=>$alive,'response_ms'=>round((microtime(true)-$then)*1000,2),'ports'=>[],'scanned_at'=>gmdate('c'),'host'=>null];
    }
    private function ping(string $ip): bool {
        foreach(['/usr/sbin/fping','/usr/bin/fping','/sbin/fping'] as $fping) {
            if (!is_executable($fping)) continue;
            // $ip originates only from long2ip() after range validation; escaping is retained as a hard boundary.
            @exec(escapeshellcmd($fping).' -c 1 -t 350 '.escapeshellarg($ip).' 2>&1', $ignore, $code);
            return $code === 0;
        }
        return false;
    }
}
