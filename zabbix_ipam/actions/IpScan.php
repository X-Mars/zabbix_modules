<?php
namespace Modules\ZabbixIpam\Actions;
use CController, CControllerResponseData;
require_once dirname(__DIR__).'/lib/IpStorage.php';require_once dirname(__DIR__).'/lib/LanguageManager.php';
use Modules\ZabbixIpam\Lib\IpStorage;use Modules\ZabbixIpam\Lib\LanguageManager;
class IpScan extends CController {
 public function init():void{if(method_exists($this,'disableCsrfValidation'))$this->disableCsrfValidation();elseif(method_exists($this,'disableSIDvalidation'))$this->disableSIDvalidation();}
 protected function checkInput():bool{return $this->validateInput(['taskid'=>'string','status'=>'in all,pending,running,completed,failed,stopped','search'=>'string']);}
 protected function checkPermissions():bool{return $this->getUserType()>=USER_TYPE_ZABBIX_ADMIN;}
 protected function doAction():void{$s=new IpStorage();$id=$this->getInput('taskid','');$status=$this->getInput('status','all');$search=mb_strtolower(trim($this->getInput('search','')));$names=[];foreach($s->ranges() as $r)$names[$r['id']]=$r['name'];$tasks=[];foreach($s->tasks() as $task){$task['range_name']=$names[$task['range_id']]??$task['range_id'];if($id&&$task['id']!==$id)continue;if($status!=='all'&&$task['status']!==$status)continue;if($search&&strpos(mb_strtolower($task['id'].' '.$task['range_name']),$search)===false)continue;$tasks[]=$task;}$this->setResponse(new CControllerResponseData(['title'=>LanguageManager::t('Task Management'),'tasks'=>$tasks,'selected'=>$id,'filters'=>['status'=>$status,'search'=>$search]]));}
}
