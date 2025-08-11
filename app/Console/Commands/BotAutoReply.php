<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\AiSetting;
use App\Services\FacebookClient;

class BotAutoReply extends Command
{
    protected $signature = 'bot:auto-reply {--since-minutes=60} {--ingest-only} {--dry} {--debug} {--source=page} {--post=}';
    protected $description = 'Citește comentarii și (opțional) răspunde automat';

    public function handle(): int
    {
        $debug  = (bool)$this->option('debug');
        $source = strtolower($this->option('source'));
        $onlyPost = $this->option('post');
        $minutes = (int)$this->option('since-minutes');
        $since = now()->subMinutes($minutes);

        $cfg = AiSetting::first()?->config ?? [];
        $pageToken = $cfg['page_token'] ?? null;
        $openaiKey = $cfg['openai_key'] ?? env('OPENAI_API_KEY');

        if (!$pageToken) { $this->error('Lipsă Facebook Page Access Token.'); return 1; }
        if (!$openaiKey) { $this->warn('Lipsă OPENAI_API_KEY – voi răspunde cu fallback.'); }

        $fb = new FacebookClient($pageToken);
        $ai = $openaiKey ? \OpenAI::client($openaiKey) : null;
        $textModel = $cfg['text_model'] ?? 'gpt-4o-mini';

        // ce postări citim
        $postIds = [];
        if ($onlyPost) {
            $postIds = [$onlyPost];
        } else {
            if ($source === 'page' || $source === 'all') {
                $pageId = $fb->getPageId();
                if ($pageId) {
                    $posts = $fb->listPosts($pageId, ['since' => $since->timestamp, 'limit' => 25]);
                    foreach ($posts as $p) if (!empty($p['id'])) $postIds[] = $p['id'];
                }
            }
            if ($source === 'mixpost' || $source === 'all') {
                $mix = DB::table('post_targets as t')
                    ->join('posts as p','p.id','=','t.post_id')
                    ->select('t.provider_post_id')
                    ->whereNotNull('t.provider_post_id')
                    ->where(function($q) use($since){
                        $q->where('p.published_at','>=',$since)->orWhere('p.created_at','>=',$since);
                    })
                    ->orderByDesc('p.published_at')
                    ->limit(25)->pluck('provider_post_id')->all();
                $postIds = array_merge($postIds, $mix);
            }
        }
        $postIds = array_values(array_unique(array_filter($postIds)));
        $this->info('Postări de verificat: '.count($postIds));

        foreach ($postIds as $pid) {
            $comments = $fb->getComments($pid, ['since'=>$since->timestamp]);
            if ($debug) $this->line(" - {$pid} | comments=".count($comments));

            foreach ($comments as $c) {
                $cid = $c['id'] ?? null;
                $msg = trim($c['message'] ?? '');
                if (!$cid || $msg==='') continue;

                // deja logat?
                if (DB::table('comment_logs')->where('comment_id',$cid)->exists()) {
                    if ($debug) $this->line("   · skip seen: {$cid}");
                    continue;
                }

                if ($this->option('ingest-only')) {
                    DB::table('comment_logs')->insert([
                        'page_id' => explode('_',$pid)[0] ?? '',
                        'post_id' => $pid,
                        'comment_id' => $cid,
                        'from_id' => $c['from']['id'] ?? null,
                        'message' => $msg,
                        'status' => 'fetched',
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                    continue;
                }

                // răspuns
                $reply = 'Mulțumim pentru mesaj! Revenim curând cu detalii.';
                if ($ai) {
                    try {
                        $res = $ai->chat()->create([
                            'model' => $textModel,
                            'messages' => [
                                ['role'=>'system','content'=>'Răspunde scurt, prietenos, politicos pentru un brand tech.'],
                                ['role'=>'user','content'=>"Comentariu: {$msg}\nScrie un răspuns (1-2 fraze)."],
                            ],
                            'temperature' => 0.6,
                        ]);
                        $reply = trim($res->choices[0]->message->content ?? $reply);
                    } catch (\Throwable $e) { report($e); }
                }

                if ($this->option('dry')) {
                    DB::table('comment_logs')->insert([
                        'page_id'=>explode('_',$pid)[0] ?? '',
                        'post_id'=>$pid,'comment_id'=>$cid,'from_id'=>$c['from']['id']??null,
                        'message'=>$msg,'reply'=>$reply,'status'=>'dry',
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                    continue;
                }

                $rid = $fb->replyToComment($cid, $reply);
                DB::table('comment_logs')->insert([
                    'page_id'=>explode('_',$pid)[0] ?? '',
                    'post_id'=>$pid,'comment_id'=>$cid,'from_id'=>$c['from']['id']??null,
                    'message'=>$msg,'reply'=>$reply,'reply_id'=>$rid,'status'=>$rid?'replied':'error',
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
        }
        return 0;
    }
}
