<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use Illuminate\Http\Request;

class ApiLogController extends Controller
{
    // public function postMethod()
    // {
    //     return ApiLog::where('method', 'POST')->get();
    // }
    // public function getMethod() {
    //     return ApiLog::where('method','GET')->get();
    // }
    // public function updateMethod()
    // {
    //     return ApiLog::where('method','UPDATE')->get();
    // }
    // public function deleteMethod()
    // {
    //     return ApiLog::where('method','DELETE')->get();
    // }
    // public function fetchById($id)
    // {
    //     return ApiLog::findorFail($id);
    // }


    // ✅ Correct
public function index(Request $request)
{
    $query = ApiLog::query();

    if ($request->has('method')) {
        $query->where('method', strtoupper($request->input('method')));
    }

    if ($request->has('status')) {
        $query->where('status_code', $request->status);
    }

    if ($request->has('from') && $request->has('to')) {
        $query->whereBetween('created_at', [
            $request->from . ' 00:00:00',
            $request->to   . ' 23:59:59'
        ]);
    }

    return response()->json($query->latest()->get()); // ✅
}
}
