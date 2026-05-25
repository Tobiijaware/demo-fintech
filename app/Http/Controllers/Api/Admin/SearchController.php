<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Services\Governance\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends ApiController
{
    public function __construct(private SearchService $search) {}

    public function index(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');

        return $this->success([
            'query' => $query,
            'results' => $this->search->search(auth('api')->user(), $query),
        ]);
    }
}
