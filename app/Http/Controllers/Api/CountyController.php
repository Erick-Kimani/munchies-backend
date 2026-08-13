<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\County;

class CountyController extends Controller
{
    /**
     * GET /api/counties
     * Used by every public dropdown (Home, Categories, Buy, Rent) AND
     * by Admin.vue — both just filter this same list by `status`.
     */
    public function index()
    {
        return County::orderBy('name')->get(['id', 'name', 'status']);
    }

    /**
     * PATCH /api/counties/{county}/restore
     * Admin-only — protect this route with your auth/admin middleware.
     */
    public function restore(County $county)
    {
        $county->update(['status' => 'active']);

        return $county;
    }

    /**
     * PATCH /api/counties/{county}/pull-down
     * Admin-only — protect this route with your auth/admin middleware.
     */
    public function pullDown(County $county)
    {
        $county->update(['status' => 'pulled_down']);

        return $county;
    }
}