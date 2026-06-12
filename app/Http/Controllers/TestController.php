<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestController extends Controller
abine
{
    public function getting(Request $request)
    {
        return response()->json([
            'message' => 'Hello Abi'
        ]);
    }

    public function posting(Request $request)
    {
        return response()->json([
            'message' => 'Student Created',
            'data' => $request->all()
        ]);
    }

    public function putting(Request $request, $id)
    {
        return response()->json([
            'message' => 'Student Updated',
            'id' => $id,
            'data' => $request->all()
        ]);
    }

    public function deleting($id)
    {
        return response()->json([
            'message' => 'Student Deleted',
            'id' => $id
        ]);
    }

    // public function month($id)
    // {
    //     if ($id == 1)
    //         return 'jan';
    //     else if ($id == 2)
    //         return 'feb';
    //     else if ($id == 3)
    //         return 'mar';
    // }

    public function error()
    {
        return response()->json([
            'error' => 'Something went wrong'
        ], 500);
    }
}
