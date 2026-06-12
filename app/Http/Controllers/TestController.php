<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;

class TestController extends Controller

{
    public function getting(Request $request)
    {
        return response()->json([
            'message' => 'Hello Abi'
        ]);
    }

    public function posting(Request $request)
    {
        $student = Student::create($request->all());

        return response()->json([
            'message' => 'Student Created',
            'data'    => $student
        ]);
    }

    public function putting(Request $request, $id)
    {
        $student = Student::findorFail($id);
        $student->update($request->all());
        return response()->json(
            [
                'message' => 'updated successfully',
                'data' => $student,
            ]
        );
    }

    public function deleting($id)
    {
        $student=Student::findorFail($id);
        $student->delete();
        return response([
              'message'=>'deleted',
              'id'=>$id
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
