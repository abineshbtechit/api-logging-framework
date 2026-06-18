<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Note;

class NoteController extends Controller
{
    // Student & Professor
    public function index()
    {
        return response()->json(
            Note::with('user')->latest()->get()
        );
    }

    // Student & Professor
    public function show($id)
    {
        $note = Note::with('user')->find($id);

        if (!$note) {
            return response()->json([
                'message' => 'Note Not Found'
            ], 404);
        }

        return response()->json($note);
    }

    // Professor Only
    public function store(Request $request)
    {
        if ($request->user()->role !== 'professor') {
            return response()->json([
                'message' => 'Access Denied'
            ], 403);
        }

        $request->validate([
            'title' => 'required',
            'content' => 'required'
        ]);

        $note = Note::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'content' => $request->input('content')
        ]);

        return response()->json([
            'message' => 'Note Created',
            'data' => $note
        ], 201);
    }

    // Professor Only
    public function update(Request $request, $id)
    {
        if ($request->user()->role !== 'professor') {
            return response()->json([
                'message' => 'Access Denied'
            ], 403);
        }

        $note = Note::find($id);

        if (!$note) {
            return response()->json([
                'message' => 'Note Not Found'
            ], 404);
        }

        $note->update($request->only([
            'title',
            'content'
        ]));

        return response()->json([
            'message' => 'Note Updated',
            'data' => $note
        ]);
    }

    // Professor Only
    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'professor') {
            return response()->json([
                'message' => 'Access Denied'
            ], 403);
        }

        $note = Note::find($id);

        if (!$note) {
            return response()->json([
                'message' => 'Note Not Found'
            ], 404);
        }

        $note->delete();

        return response()->json([
            'message' => 'Note Deleted'
        ]);
    }
}