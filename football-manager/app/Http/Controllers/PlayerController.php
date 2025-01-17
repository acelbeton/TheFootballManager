<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlayerController extends Controller
{
    public function index()
    {
        return view('players.index');
    }

    public function create()
    {
        return view('players.create');
    }

    public function edit($id)
    {
        return view('players.edit', compact('id'));
    }
}
