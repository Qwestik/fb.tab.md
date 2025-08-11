<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\CommentLog;
use App\Models\PostChannel;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class BotAutoReply extends Command
{
    protected $signature = 'bot:auto-reply 
        {--since=7d : Interval pentru postări publicate (ex: 2h, 3d)}
        {--limit=100 : Câte comentarii să ia pe post}
        {--dry : Nu postează, doar afișează}
        {--debug : Mesaje verbose}';

    protected $description = 'Citește comentarii la postările publicate și răspunde automat doar când ultimul mesaj din thread e al utilizatorului.';

    public function handle(): int
    {
        $since = $this->parseSince($this->option('since'));
        $limit = (int)$this->option('limit');
        $dry   = (bool)$this->option('dry');
        $debug = (bool)$this->option('debug');

        $channels = Channel::where('platform', 'fb')->get();
        if ($channels->isEmpty()) {
            $this->warn('Nu există canale Facebook.');
            return 0;
        }

        foreach ($channels as $ch) {
            if (!$ch->page_id || !$ch->access_token) {
                $this->warn("Canal #{$ch->id} lipsesc page_id / access_token.");
                continue;
            }

            // Postări publicate pentru canalul curent
            $posts = PostChannel::query()
                ->where('channel_id', $ch->id)
                ->where('status', 'published')
                ->where(function ($q) {
                    $q->whereNotNull('provider_post_id')
                      ->orWhereNotNull('fb_post_id');
                })
                // dacă nu ai 'published_at' setat pe PostChannel, folosim updated_at ca fallback
                ->where(function ($q) use ($since) {
                    $q->where('published_at', '>=', $since)
                      ->orWhere('updated_at', '>=', $since);
                })
                ->orderByDesc('published_at')
                ->orderByDesc('updated_at')
                ->get();

            $this->info("Canal {$ch->name} (#{$ch->id}) — postări: ".$posts->count());

            foreach ($posts as $pc) {
                $postId = $pc->provider_post_id ?: $pc->fb_post_id;
                if (!$postId) { if ($debug) $this->line('  • Post fără provider_post_id/fb_post_id'); continue; }

                $this->line("  • Post {$postId}");

                // 1) comentarii top-level
                $top = Http::timeout(20)->get(
                    "https://graph.facebook.com/v23.0/{$postId}/comments",
                    [
                        'access_token' => $ch->access_token,
                        'order'        => 'chronological',
                        'filter'       => 'toplevel',
                        'limit'        => $limit,
                        'fields'       => 'id,from{id,name},message,created_time',
                    ]
                );
                if (!$top->successful()) {
                    $this->warn('    ! Eroare FB (toplevel): '.$top->status().' '.$top->body());
                    continue;
                }

                $topComments = collect($top->json('data', []));
                if ($topComments->isEmpty()) { if ($debug) $this->line('    (fără top-level)'); continue; }

                // 2) pentru fiecare thread: răspunde DOAR dacă ultimul mesaj e al utilizatorului
                foreach ($topComments as $root) {
                    $thread = $this->fetchThread($root, $ch->access_token); // [root, reply1, reply2,...] cronologic
                    if ($thread->isEmpty()) continue;

                    $last     = $thread->last();
                    $lastId   = data_get($last, 'id');
                    $lastFrom = data_get($last, 'from.id');
                    $lastMsg  = trim((string) data_get($last, 'message', ''));

                    // dacă ultimul din thread este pagina -> noi am vorbit ultimii, nu mai răspundem acum
                    if ($lastFrom === $ch->page_id) { if ($debug) $this->line('    - skip (last=page)'); continue; }
                    if ($lastMsg === '')            { if ($debug) $this->line('    - skip empty'); continue; }

                    // evită dublurile: dacă am răspuns deja FIX la acest comentariu
                    if (CommentLog::where('comment_id', $lastId)->exists()) {
                        if ($debug) $this->line("    - skip (logged) {$lastId}");
                        continue;
                    }

                    $reply = $this->generateReply($lastMsg);
                    if ($debug) $this->line("    -> reply: ".mb_substr($reply, 0, 120).'…');

                    $replyId = null; $status = 'sent'; $error = null;

                    if (!$dry) {
                        $r = Http::asForm()->timeout(20)->post(
                            "https://graph.facebook.com/v23.0/{$lastId}/comments",
                            ['access_token' => $ch->access_token, 'message' => $reply]
                        );
                        if ($r->successful()) {
                            $replyId = data_get($r->json(), 'id');
                            if ($debug) $this->line("    + FB ok: reply_id={$replyId}");
                        } else {
                            $status = 'error';
                            $error  = $r->status().' '.$r->body();
                            $this->warn('    ! FB error: '.$error);
                        }
                    }

                    CommentLog::create([
                        'channel_id'     => $ch->id,
                        'post_id'        => $pc->post_id,
                        'post_channel_id'=> $pc->id,
                        'page_id'        => $ch->page_id,
                        'post_fb_id'     => $postId,
                        'comment_id'     => $lastId,
                        'from_id'        => $lastFrom,
                        'message'        => $lastMsg,
                        'reply'          => $reply,
                        'reply_id'       => $replyId,
                        'status'         => $status,
                        'meta'           => $error ? ['fb_error' => $error] : null,
                    ]);
                }
            }
        }

        return 0;
    }

    /**
     * Ia întreg thread-ul: comentariul rădăcină + răspunsuri (cronologic).
     */
    protected function fetchThread(array $root, string $token): Collection
    {
        $rootId = data_get($root, 'id');
        $items  = collect([$root]);

        $r = Http::timeout(20)->get(
            "https://graph.facebook.com/v23.0/{$rootId}/comments",
            [
                'access_token' => $token,
                'order'        => 'chronological',
                'limit'        => 100,
                'fields'       => 'id,from{id,name},message,created_time',
            ]
        );

        if ($r->successful()) {
            $items = $items->merge($r->json('data', []));
        }

        return $items->sortBy(fn ($c) => strtotime((string) data_get($c, 'created_time')))->values();
    }

    protected function parseSince(string $s): Carbon
    {
        $s = trim($s);
        if (preg_match('/^(\d+)\s*h$/', $s, $m)) return now()->subHours((int) $m[1]);
        if (preg_match('/^(\d+)\s*d$/', $s, $m)) return now()->subDays((int) $m[1]);
        return now()->subDays(7);
    }

    protected function generateReply(string $msg): string
    {
        $apiKey = config('services.openai.key') ?? env('OPENAI_API_KEY');
        $model  = env('AI_MODEL_TEXT', 'gpt-4o-mini');

        $fallback = "Mulțumim pentru comentariu! Dacă ai întrebări sau dorești detalii suplimentare, scrie-ne în privat.";

        if (!$apiKey) return $fallback;

        try {
            $client = \OpenAI::client($apiKey);
            $res = $client->chat()->create([
                'model' => $model,
                'messages' => [
                    ['role'=>'system','content'=>'Răspunzi scurt, prietenos și util pentru o pagină Xiaomi Moldova (tab.md). Fără promisiuni false, fără emoji excesive. Ton profesionist, cald.'],
                    ['role'=>'user','content'=>"Scrie un răspuns de 1–2 fraze la acest comentariu de pe Facebook:\n\"{$msg}\"\nDacă e întrebare, răspunde direct și invită politicos la mesaj privat pentru detalii/preț."],
                ],
                'temperature' => 0.6,
                'max_tokens'  => 120,
            ]);
            return trim($res->choices[0]->message->content ?? $fallback);
        } catch (\Throwable $e) {
            report($e);
            return $fallback;
        }
    }
}
