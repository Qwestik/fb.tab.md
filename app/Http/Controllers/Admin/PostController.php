<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Channel, Media, Post, PostChannel};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PostController extends Controller
{
    public function index() {
        $posts = Post::orderByDesc('created_at')->paginate(20);
        return view('admin.posts.index', compact('posts'));
    }

    public function create() {
        $post = new Post();
        $channels = Channel::orderBy('platform')->get();
        return view('admin.posts.edit', compact('post','channels'));
    }

    public function store(Request $r) {
        $data = $this->validated($r);
        $post = Post::create($data);

        $this->syncChannels($post, $r->input('channel_ids', []));
        $this->handleUpload($post, $r);

        return redirect()->route('admin.posts.edit', $post)->with('ok','Creat.');
    }

    public function edit(Post $post) {
        $channels = Channel::orderBy('platform')->get();
        $selected = $post->channels()->pluck('channels.id')->all();
        return view('admin.posts.edit', compact('post','channels','selected'));
    }

    public function update(Request $r, Post $post) {
        $post->update($this->validated($r));
        $this->syncChannels($post, $r->input('channel_ids', []));
        $this->handleUpload($post, $r);

        return back()->with('ok','Salvat.');
    }

    public function destroy(Post $post) {
        $post->delete();
        return redirect()->route('admin.posts.index')->with('ok','Șters.');
    }

    public function publish(Post $post) {
        // marcăm pending; comanda bot:publish-due îl va ridica imediat
        $post->update(['status' => 'scheduled', 'scheduled_at' => now()]);
        return back()->with('ok','Trimis către publisher.');
    }

    protected function validated(Request $r): array {
        return $r->validate([
            'title' => 'nullable|string|max:255',
            'body'  => 'nullable|string',
            'status'=> 'required|in:draft,scheduled,published',
            'scheduled_at' => 'nullable|date',
        ]);
    }

    protected function syncChannels(Post $post, array $ids): void {
        // ștergem ce nu e selectat, adăugăm ce e nou
        PostChannel::where('post_id',$post->id)
            ->whereNotIn('channel_id',$ids)->delete();

        foreach ($ids as $id) {
            PostChannel::firstOrCreate([
                'post_id' => $post->id,
                'channel_id' => $id,
            ]);
        }
    }

    protected function handleUpload(Post $post, Request $r): void {
        if ($r->hasFile('image') && $r->file('image')->isValid()) {
            $path = $r->file('image')->store('posts/'.now()->format('Y/m/d'), 'public');
            Media::create([
                'post_id' => $post->id,
                'path'    => $path,
                'disk'    => 'public',
                'mime'    => $r->file('image')->getMimeType(),
            ]);
        }
    }
}
