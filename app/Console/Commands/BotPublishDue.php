<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{Post,PostVersion,PostTarget,SocialAccount,Medium};
use App\Services\FacebookClient;

class BotPublishDue extends Command
{
    protected $signature = 'bot:publish-due {--debug}';
    protected $description = 'Publică postările ajunse la termen';

    public function handle(): int
    {
        $due = Post::where('status','scheduled')->where('scheduled_at','<=',now())->limit(20)->get();
        $this->info('De publicat: '.$due->count());

        foreach ($due as $post) {
            $targets = PostTarget::where('post_id',$post->id)->get();

            foreach ($targets as $t) {
                $acc = SocialAccount::find($t->account_id);
                $ver = PostVersion::where(['post_id'=>$post->id,'account_id'=>$t->account_id])->first();
                if (!$acc || !$ver) { $t->update(['status'=>'failed']); continue; }

                $fb = new FacebookClient($acc->access_token);
                $pageId = $acc->page_id;

                $mediaId = $ver->media[0] ?? null;
                $imgPath = null;
                if ($mediaId) {
                    $m = Medium::find($mediaId);
                    if ($m) $imgPath = storage_path('app/public/'.$m->path);
                }

                $providerId = $imgPath
                    ? $fb->publishPhoto($pageId, strip_tags($ver->body), $imgPath)
                    : $fb->publishText($pageId, strip_tags($ver->body));

                if ($providerId) {
                    $t->update(['status'=>'published','provider_post_id'=>$providerId]);
                    $post->update(['status'=>'published','published_at'=>now()]);
                } else {
                    $t->update(['status'=>'failed','errors'=>['publish'=>'failed']]);
                    $post->update(['status'=>'failed']);
                }
            }
        }
        return 0;
    }
}
