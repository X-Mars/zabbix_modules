<?php
namespace Modules\ZabbixIpam\Lib;

/** JSON repository. Every mutation uses a lock + atomic rename so cron and UI may run together. */
class IpStorage {
    private $root;
    public function __construct(?string $root = null) { $this->root = $root ?: dirname(__DIR__) . '/data'; $this->ensure(); }
    public function root(): string { return $this->root; }
    private function ensure(): void { foreach ([$this->root, $this->root.'/scan_tasks', $this->root.'/results'] as $d) if (!is_dir($d)) @mkdir($d, 0770, true); }
    private function file(string $name): string { return $this->root.'/'.$name; }
    private function read(string $file, $default = []) { $raw = @file_get_contents($file); $data = $raw === false ? null : json_decode($raw, true); return is_array($data) ? $data : $default; }
    private function write(string $file, $value): void {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        if ($json === false) { throw new \RuntimeException('Invalid JSON'); }
        $tmp = $file.'.'.bin2hex(random_bytes(8)).'.tmp';
        try {
            if (@file_put_contents($tmp, $json, LOCK_EX) !== strlen($json) || !@rename($tmp, $file)) {
                throw new \RuntimeException('Cannot write config file');
            }
        } finally { if (is_file($tmp)) { @unlink($tmp); } }
    }
    public function ranges(): array { return $this->read($this->file('ip_ranges.json')); }
    public function range(string $id): ?array { foreach ($this->ranges() as $r) if (($r['id'] ?? '') === $id) return $r; return null; }
    public function saveRange(array $range): array {
        $lock = @fopen($this->file('ip_ranges.lock'), 'c+');
        if (!$lock) { throw new \RuntimeException('Data directory is not writable.'); }
        try {
            if (!flock($lock, LOCK_EX)) { throw new \RuntimeException('Cannot lock config file'); }
            $all = $this->ranges();
            $range['id'] = !empty($range['id']) ? $range['id'] : bin2hex(random_bytes(8));
            $range['updated_at'] = gmdate('c'); $found = false;
            foreach ($all as &$row) {
                if ($row['id'] === $range['id']) { $range = array_merge($row, $range); $row = $range; $found = true; }
            }
            unset($row);
            if (!$found) { $range['created_at'] = gmdate('c'); $all[] = $range; }
            $this->write($this->file('ip_ranges.json'), $all);
            return $range;
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
    public function deleteRange(string $id): void { $this->write($this->file('ip_ranges.json'), array_values(array_filter($this->ranges(), function ($r) use ($id) { return ($r['id']??'')!==$id; }))); }
    public function task(string $id): ?array { $f=$this->file('scan_tasks/'.$id.'.json'); return is_file($f)?$this->read($f,null):null; }
    public function saveTask(array $task): void { $this->write($this->file('scan_tasks/'.$task['id'].'.json'),$task); }
    public function tasks(): array { $out=[]; foreach(glob($this->file('scan_tasks/*.json'))?:[] as $f){$t=$this->read($f,null);if($t)$out[]=$t;} usort($out,function ($a,$b) { return strcmp($b['created_at']??'', $a['created_at']??''); });return $out; }
    public function resultFile(string $taskId): string { return $this->file('results/'.$taskId.'.json'); }
    public function saveResults(string $id,array $rows):void{$this->write($this->resultFile($id),$rows);}
    public function results(string $id):array{return $this->read($this->resultFile($id));}
}
