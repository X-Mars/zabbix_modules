<?php

namespace Modules\ZabbixIm\Lib;

interface ImProviderInterface {

    /**
     * @return array<int, array{id:string,name:string,parent_id:string}>
     */
    public function getDepartments(): array;

    /**
     * @return array<int, array{id:string,name:string,username:string,email:string,mobile:string}>
     */
    public function getDepartmentUsers(string $departmentId): array;

    /**
     * 本次请求内 IM API 调用调试日志（含原始 HTTP 响应）
     *
     * @return array<int, array<string, mixed>>
     */
    public function getApiDebugLog(): array;
}
