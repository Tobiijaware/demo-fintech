<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends ApiController
{
    public function index(): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy('group');

        return $this->success($permissions);
    }
}
