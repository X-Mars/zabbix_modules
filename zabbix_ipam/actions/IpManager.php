<?php
namespace Modules\ZabbixIpam\Actions;
use CController, CControllerResponseData;
require_once dirname(__DIR__).'/lib/IpStorage.php';require_once dirname(__DIR__).'/lib/IpScanner.php';require_once dirname(__DIR__).'/lib/LanguageManager.php';
use Modules\ZabbixIpam\Lib\{IpStorage,IpScanner,LanguageManager};
class IpManager extends CController {
 public function init():void{if(method_exists($this,'disableCsrfValidation'))$this->disableCsrfValidation();elseif(method_exists($this,'disableSIDvalidation'))$this->disableSIDvalidation();}
 protected function checkInput():bool{return $this->validateInput(['rangeid'=>'string','status'=>'in all,enabled,disabled,pending,running,completed,failed','search'=>'string']);}
 protected function checkPermissions():bool{return $this->getUserType()>=USER_TYPE_ZABBIX_ADMIN;}
 protected function doAction():void{$s=new IpStorage();$tasks=$s->tasks();$latest=[];foreach($tasks as $task)if(!isset($latest[$task['range_id']]))$latest[$task['range_id']]=$task;$rows=[];foreach($s->ranges() as $range){try{$parsed=IpScanner::parseRange($range['range']);$total=$parsed['count'];}catch(\Throwable $e){$total=0;}$task=$latest[$range['id']]??null;$rows[]=['range'=>$range,'task'=>$task,'total'=>$task['total']??$total,'alive'=>$task['alive']??0,'scanned'=>$task['scanned']??0,'task_status'=>$task['status']??'never'];}$selected=$this->getInput('rangeid','');$status=$this->getInput('status','all');$search=mb_strtolower(trim($this->getInput('search','')));$rows=array_values(array_filter($rows,function($row)use($selected,$status,$search){$r=$row['range'];$matchStatus=$status==='all'||($status==='enabled'&&!empty($r['enabled']))||($status==='disabled'&&empty($r['enabled']))||$row['task_status']===$status;return(!$selected||$r['id']===$selected)&&$matchStatus&&(!$search||strpos(mb_strtolower(($r['name']??'').' '.($r['range']??'')),$search)!==false);}));$this->setResponse(new CControllerResponseData(['title'=>LanguageManager::t('IPAM'),'rows'=>$rows,'all_ranges'=>$s->ranges(),'filters'=>['rangeid'=>$selected,'status'=>$status,'search'=>$search]]));}
}
