<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index()
    {
        $channels = Channel::orderBy('platform')->orderBy('name')->paginate(25);
        return view('admin.channels.index', compact('channels'));
    }

    public function create()
    {
        $channel = new Channel(['platform' => 'fb']);
        return view('admin.channels.edit', compact('channel'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Channel::create($data);
        return redirect()->route('admin.channels.index')->with('ok', 'Channel adăugat.');
    }

    public function edit(Channel $channel)
    {
        return view('admin.channels.edit', compact('channel'));
    }

    public function update(Request $request, Channel $channel)
    {
        $channel->update($this->validated($request));
        return redirect()->route('admin.channels.index')->with('ok', 'Salvat.');
    }

    public function destroy(Channel $channel)
    {
        $channel->delete();
        return back()->with('ok', 'Șters.');
    }

    protected function validated(Request $r): array
    {
        return $r->validate([
            'platform'        => 'required|in:fb,ig',
            'page_id'         => 'required|string|max:255',
            'name'            => 'nullable|string|max:255',
            'access_token'    => 'nullable|string',
            'token_expires_at'=> 'nullable|date',
        ]);
    }
}
