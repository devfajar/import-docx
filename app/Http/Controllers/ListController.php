<?php

namespace App\Http\Controllers;

use App\Models\Lists;
use Illuminate\Http\Request;

class ListController extends Controller
{
    public function index()
    {
        $list = Lists::all();
        return response()->json([
            'data' => $list,
        ]);
    }
    public function store(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string',
        ]);

        $list = Lists::create([
            'title' => $request->title,
            'board_id' => $id
        ]);

        return response()->json([
            'List Created Successfully',
        ]);
    }

    public function delete($id)
    {
        $list = Lists::findOrFail($id);
        $list->delete();
    }
}
