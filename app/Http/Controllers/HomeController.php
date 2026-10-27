<?php

namespace App\Http\Controllers;

use App\Services\AttendanceBranchOptions;
use App\Services\AttendanceDashboard;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request, AttendanceDashboard $dashboard)
    {
        $filters = $request->validate(['branch_id' => ['nullable', 'integer', 'min:1']]);
        $branchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
        return view('home', $dashboard->build($branchId) + [
            'data' => ['page_title' => 'Inicio'], 'branches' => AttendanceBranchOptions::all(), 'branchId' => $branchId,
        ]);
    }
}
