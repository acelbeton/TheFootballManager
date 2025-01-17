<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlayerController extends Controller
{
    /**
     * Display the list of players.
     */
    public function index()
    {
        return view('players.index');
    }

    /**
     * Show the form to create a new player.
     */
    public function create()
    {
        return view('players.create');
    }

    /**
     * Show the form to edit an existing player.
     */
    public function edit($id)
    {
        return view('players.edit', compact('id'));
    }
}
