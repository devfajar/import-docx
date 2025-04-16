<?php

namespace App\Http\Controllers;

use App\Models\Board;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    public function index()
    {
        $boards = Board::all();
        return response()->json([
            'data' => $boards
        ]);
    }

    public function create(Request $request)
    {
        $board = new Board();
        $board->name = $request->input('name');
        $board->save();

        return response()->json([
            'data' => $board,
        ]);
    }

    public function delete($id)
    {
        $board = Board::find($id);
        $board->delete();

        return response()->json([
            'message' => 'Deleted Successfully',
        ]);
    }
}
