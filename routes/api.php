<?php

use App\Http\Controllers\BoardController;
use App\Http\Controllers\ExamJsonController;
use App\Http\Controllers\ListController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/exam-json', [ExamJsonController::class, 'convertToJson']);
Route::post('/debug-docx', [ExamJsonController::class, 'debugDocx']);

