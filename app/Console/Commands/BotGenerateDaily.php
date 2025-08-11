<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\{Post,SocialAccount,PostVersion,PostTarget};
use App\Services\AIService;
use App\Services\ImageStorage;

class BotGenerateDaily extends Command
{
    protected $signature = 'bot:generate-daily {--slots=} {--date=} {--debug}';
    protected $description = 'Generează postări programate (AI text + imagine)';

    public function handle(): int
    {
        $tz = config('app.timezone','Europe/Chisinau');
        $now  = now($tz);
        $day  = $this->option('date') ?: $now->toDateString();
        $base = Carbon::parse($day, $tz);
        $slots = $this->option('slots') ? array_map('trim', explode(',', $this->option('slots'))) : ['09:30','19:00'];

        $acc = SocialAccount::first();
        if (!$acc) { $this->error('Nu există rând în social_accounts.'); return 1; }

        $ai = new AIService();

        foreach ($slots as $raw) {
            $when = preg_match('/^\d{1,2}:\d{2}$/',$raw) ? $base->copy()->setTimeFromTimeString($raw) : Carbon::parse($raw,$tz);

            $caption = $ai->generateCaption('engagement');
            [$imgAbs] = $ai->generateImageFromCaption($caption, 'engagement', (bool)$this->option('debug'));

            $post = Post::create(['uuid'=>Str::uuid(),'status'=>'scheduled','scheduled_at'=>$when,'timezone'=>$tz]);
            PostTarget::create(['post_id'=>$post->id,'account_id'=>$acc->id,'status'=>'scheduled']);

            $mediaIds = [];
            if ($imgAbs) $mediaIds[] = ImageStorage::storeLocalJpeg($imgAbs);

            PostVersion::create([
                'post_id'=>$post->id,'account_id'=>$acc->id,
                'body'=>'<div>'.e($caption).'</div>','media'=>$mediaIds
            ]);

            $this->info('Creat & programat: '.$when->toDateTimeString());
        }
        return 0;
    }
}
