<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\Channel;
use App\Models\PostChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class BotPublishDue extends Command
{
    protected $signature = 'bot:publish-due {--debug}';
    protected $description = 'Publică postările ajunse la termen pe canalele configurate';

    public function handle(): int
    {
        $debug = $this->option('debug');

        $due = Post::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->get();

        $this->line('De publicat: '.$due->count());

        foreach ($due as $post) {
            $targets = PostChannel::where('post_id', $post->id)->get();

            if ($targets->isEmpty()) {
                $this->warn(" - Post #{$post->id} nu are canale selectate (post_channels). Sar.");
                continue;
            }

            // pregătește mesajul
            $message = trim(($post->title ? ($post->title."\n\n") : '').(string)$post->body);
            $imagePath = null;
            $media = $post->media()->first();
            if ($media) {
                try {
                    $imagePath = Storage::disk($media->disk)->path($media->path);
                    if (!is_file($imagePath)) {
                        $this->warn("   ! Fișier media lipsă: {$imagePath}");
                        $imagePath = null;
                    }
                } catch (\Throwable $e) {
                    $this->warn("   ! Eroare media: ".$e->getMessage());
                    $imagePath = null;
                }
            }

            foreach ($targets as $t) {
                $channel = Channel::find($t->channel_id);
                if (!$channel) {
                    $this->warn("   ! Channel #{$t->channel_id} lipsă. Marchez failed.");
                    $t->status = 'failed';
                    $t->error = 'channel not found';
                    $t->save();
                    continue;
                }

                if ($channel->platform !== 'fb') {
                    $this->line("   ~ Sar platforma {$channel->platform} (nu e fb încă).");
                    continue;
                }

                try {
                    $resp = $this->publishToFacebookPage(
                        $channel->page_id,
                        $channel->access_token,
                        $message,
                        $imagePath,
                        $debug
                    );

                    if ($debug) {
						$this->line('   + FB resp: '.json_encode($resp));
					}

					// pentru /photos -> $resp conține:
					//   id      = id-ul obiectului foto
					//   post_id = <PAGEID>_<POSTID> (acesta e util pentru comments)
					$t->status            = 'published';
					$t->published_at      = now();
					$t->provider_post_id  = $resp['post_id'] ?? ($resp['id'] ?? null); // <PAGEID>_<POSTID> preferat pentru comments
					$t->fb_post_id        = $resp['id']      ?? null;                  // id foto (sau id-ul postării pe /feed)
					$t->last_error        = null;
					$t->save();
                } catch (\Throwable $e) {
                    $this->error('   ! FB error: '.$e->getMessage());
                    $t->status = 'failed';
					$t->last_error = ['msg' => $e->getMessage()];
					$t->save();

                }
            }

            // dacă cel puțin unul a reușit, marcăm post-ul publicat
            $anyOk = PostChannel::where('post_id',$post->id)
                ->where('status','published')->exists();

            if ($anyOk) {
                $post->status = 'published';
                $post->published_at = now();
                $post->save();
            }
        }

        return self::SUCCESS;
    }

    /**
     * Publică pe Facebook.
     * - dacă avem imagine -> /{page_id}/photos (caption + source)
     * - altfel -> /{page_id}/feed (message)
     */
    protected function publishToFacebookPage(string $pageId, string $accessToken, string $message, ?string $imagePath, bool $debug = false): array
    {
        $version = 'v23.0';

        if ($imagePath && is_file($imagePath)) {
            // Photos
            if ($debug) {
                $this->line("   -> POST /{$version}/{$pageId}/photos (cu imagine)");
            }

            $response = Http::asMultipart()
                ->attach('source', fopen($imagePath, 'r'), basename($imagePath))
                ->post("https://graph.facebook.com/{$version}/{$pageId}/photos", [
                    ['name' => 'caption', 'contents' => $message],
                    ['name' => 'published', 'contents' => 'true'],
                    ['name' => 'access_token', 'contents' => $accessToken],
                ]);
        } else {
            // Feed (text-only)
            if ($debug) {
                $this->line("   -> POST /{$version}/{$pageId}/feed (text)");
            }

            $response = Http::asForm()->post("https://graph.facebook.com/{$version}/{$pageId}/feed", [
                'message' => $message,
                'access_token' => $accessToken,
            ]);
        }

        if (!$response->ok()) {
            throw new \RuntimeException('FB error: '.$response->status().' '.$response->body());
        }

        return $response->json() ?? [];
    }
}
