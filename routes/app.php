<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NoteController;

// ---- Login / authentication ----
Route::post('/login', [AuthController::class, 'login'])->middleware('mid');
Route::post('/logout', [AuthController::class, 'logout'])->middleware(['auth:sanctum', 'mid']);
Route::get('/me', [AuthController::class, 'me'])->middleware(['auth:sanctum', 'mid']);

// ---- Notes ----
Route::middleware(['auth:sanctum', 'mid'])->group(function () {

    // Student & professor: view notes
    Route::get('/notes', [NoteController::class, 'index']);        // list all notes (student dashboard feed)
    Route::get('/notes/{id}', [NoteController::class, 'show']);     // view a single note

    // Professor only: post/manage notes
    Route::post('/notes', [NoteController::class, 'store']);        // professor posts a new note
    Route::put('/notes_u/{id}', [NoteController::class, 'update']);   // professor edits a note
    Route::delete('/notes_d/{id}', [NoteController::class, 'destroy']); // professor deletes a note
});