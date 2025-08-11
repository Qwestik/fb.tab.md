<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\Media;
use App\Models\Channel;
use App\Models\PostChannel;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BotGenerateDaily extends Command
{
    protected $signature = 'bot:generate-daily
        {--date= : Ziua (YYYY-MM-DD), implicit azi}
        {--slots= : Ore sau expresii (ex: "09:30,19:00" ori "in 5 minutes,in 2 hours")}
        {--channels=* : ID-urile din tabela channels (dacă omiți, atașează toate)}
        {--no-image : Nu genera imagine}
        {--dry : Nu scrie în DB}
        {--debug : Log detaliat}';

    protected $description = 'Generează postări programate (AI text + imagine) pe schema fbapp';

    public function handle(): int
    {
        $tz   = config('app.timezone', 'Europe/Chisinau');
        $now  = Carbon::now($tz);
        $day  = $this->option('date') ?: $now->toDateString();
        $base = Carbon::parse($day, $tz);

        $debug = (bool)$this->option('debug');
        $withImages = !$this->option('no-image');

        // slots
        $tokens = $this->option('slots')
            ? array_map('trim', explode(',', (string)$this->option('slots')))
            : ['09:30', '19:00'];

        try {
            $slots = collect($tokens)->map(fn ($t) => $this->parseSlot($base, $now, $tz, $t))->values();
        } catch (\Throwable $e) {
            $this->error('Eroare la --slots: '.$e->getMessage());
            return self::FAILURE;
        }

        // canale: dacă nu s-au dat explicit, ia toate
        $channelIds = collect($this->option('channels') ?? [])
            ->filter(fn($v) => is_numeric($v))
            ->map(fn($v) => (int)$v)
            ->all();

        $channels = empty($channelIds)
            ? Channel::query()->get()
            : Channel::query()->whereIn('id', $channelIds)->get();

        if ($channels->isEmpty()) {
            $this->warn('Nu există canale în tabela channels. Poți continua, dar postările nu vor avea ținte.');
        }

        // rulare pe sloturi
        foreach ($slots as $when) {
            // evită dubluri pe aceeași oră/minut
            $exists = Post::query()
                ->whereDate('scheduled_at', $when->toDateString())
                ->whereTime('scheduled_at', $when->format('H:i:s'))
                ->exists();

            if ($exists) {
                $this->line("Salt (există deja): ".$when->toDateTimeString());
                continue;
            }

            $theme   = $this->pickThemeForDay($when);
            $caption = $this->generateCaption($theme, $debug);

            // ---------- imagine (AI -> fallback -> placeholder) ----------
            $imageAbs = null;
            if ($withImages) {
                [$imageAbs, $dbg] = $this->generateImageFromCaption($caption, $theme, $debug);
                foreach ($dbg as $m) $this->line(' + img: '.$m);

                if (!$imageAbs) {
                    // Placeholder local (PNG) convertit la JPG + thumb
                    $png = $this->makePlaceholderPng($caption, $theme);
                    $log = [];
                    $imageAbs = $this->saveImageToPublic($png, 'png', $log);
                    foreach ($log as $m) $this->line(' + ph: '.$m);
                }
            }

            $this->line("Pregătesc: {$when->toDateTimeString()} | theme={$theme}");

            if ($this->option('dry')) {
                $this->info(' (dry-run) Nu scriu în DB.');
                continue;
            }

            // ---------- scriere în DB ----------
            $post = new Post();
            $post->uuid = (string) Str::uuid();
            $post->title = null;
            $post->body = $caption;
            $post->status = 'scheduled';
            $post->scheduled_at = $when;
            $post->save();

            if ($imageAbs && is_file($imageAbs)) {
                // Relativ față de storage/app/public
                $root = storage_path('app/public').DIRECTORY_SEPARATOR;
                $rel  = str_replace($root, '', $imageAbs);

                $media = new Media();
                $media->post_id = $post->id;
                $media->disk = 'public';
                $media->path = $rel;
                $media->mime = 'image/jpeg';
                $media->size = @filesize($imageAbs) ?: null;
                $media->save();
            }

            foreach ($channels as $ch) {
                PostChannel::create([
                    'post_id'    => $post->id,
                    'channel_id' => $ch->id,
                    'status'     => 'pending',
                ]);
            }

            $this->info("Creat & programat: {$when->toDateTimeString()} (post #{$post->id})");
        }

        return self::SUCCESS;
    }

    /* ================= Helpers ================= */

    protected function parseSlot(Carbon $base, Carbon $now, string $tz, string $t): Carbon
    {
        $s = trim(mb_strtolower($t));

        if (str_starts_with($s, 'in ')) {
            if (preg_match('/^in\s+(\d+)\s*(minutes?|minute|mins?|min|m|hours?|hour|hrs?|hr|h)?$/i', $s, $m)) {
                $n = (int)$m[1];
                $u = strtolower($m[2] ?? 'minutes');
                $isHours = in_array($u, ['h','hr','hrs','hour','hours'], true);
                return $isHours ? $now->copy()->addHours($n) : $now->copy()->addMinutes($n);
            }
            throw new \InvalidArgumentException("Slot invalid: {$t}");
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $s)) {
            return $base->copy()->setTimeFromTimeString($s);
        }

        return Carbon::parse($t, $tz);
    }

    protected function pickThemeForDay(Carbon $when): string
    {
        // L, M, J = promo; V, D = engagement; altfel educativ
        $dow = $when->dayOfWeekIso; // 1..7
        if (in_array($dow, [1,2,4], true)) return 'promo';
        return in_array($dow, [5,7], true) ? 'engagement' : 'educativ';
    }

    protected function generateCaption(string $theme, bool $debug = false): string
    {
        $fallbacks = [
            'promo'      => "Promo Xiaomi! Descoperă cele mai noi telefoane și gadgeturi. Scrie-ne în privat pentru ofertă. #tabmd #Xiaomi #Chisinau #Moldova",
            'educativ'   => "Știai că poți prelungi viața bateriei pe Xiaomi activând Battery Saver și optimizând aplicațiile în fundal?",
            'engagement' => "Ce caracteristică a telefoanelor Xiaomi te impresionează cel mai mult: camera, bateria sau designul?",
        ];

        $key = env('OPENAI_API_KEY');
        if (!$key) return $fallbacks[$theme];

        $model  = env('AI_MODEL_TEXT','gpt-4o-mini');
        $prompt = match ($theme) {
            'promo'      => "Scrie un text scurt (70–90 cuvinte) pentru promo Xiaomi (telefon/wearable/smart home) în română, ton prietenos+profesionist+ușor glumeț, include CTA „Scrie-ne în privat” și hashtags: #tabmd #Xiaomi #Chisinau #Moldova.",
            'educativ'   => "Scrie un sfat util despre folosirea telefoanelor Xiaomi (baterie/cameră/MIUI), 3–4 fraze, încheie cu o întrebare pentru comentarii. Română, concis.",
            default      => "Scrie o postare de engagement pentru comunitatea Xiaomi (întrebare/opinie rapidă), max 2–3 fraze, friendly, în română.",
        };

        try {
            $res = Http::withToken($key)
                ->timeout(45)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Scrii postări Facebook în limba română pentru brandul „tab-md” (Xiaomi Moldova). Scurt, clar, fără emoji excesive.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.8,
                ]);

            if (!$res->ok()) {
                if ($debug) $this->line(' + ai.txt: http '.$res->status().' '.$res->body());
                return $fallbacks[$theme];
            }

            $txt = trim((string) data_get($res->json(), 'choices.0.message.content'));
            return $txt !== '' ? $txt : $fallbacks[$theme];
        } catch (\Throwable $e) {
            if ($debug) $this->line(' + ai.txt: ex '.mb_substr($e->getMessage(),0,140).'…');
            return $fallbacks[$theme];
        }
    }

    /**
     * Returnează [absPath|null, debug[]]
     */
    protected function generateImageFromCaption(string $caption, string $theme, bool $debug = false): array
    {
        $log = [];
        $key = env('OPENAI_API_KEY');
        if (!$key) return [null, ['OPENAI_API_KEY missing']];

        $primary  = env('AI_MODEL_IMAGE','gpt-image-1');
        $fallback = env('AI_MODEL_IMAGE_FALLBACK','dall-e-3');
        $rawSize  = env('AI_IMAGE_SIZE','portrait'); // portrait|landscape|1024x1024 etc.

        // subject heuristic
        $cap = mb_strtolower($caption,'UTF-8');
        $subject = 'modern Xiaomi smartphone';
        if (preg_match('/(watch|band|wearable|ceas|bratara|brățară)/u',$cap))      $subject = 'Xiaomi smartwatch or fitness band';
        elseif (preg_match('/(buds|earbuds|casti|c[aă]ști)/u',$cap))               $subject = 'Xiaomi earbuds';
        elseif (preg_match('/(aspirator|robot|vacuum)/u',$cap))                    $subject = 'Xiaomi robot vacuum cleaner';
        elseif (preg_match('/(tablet|pad)/u',$cap))                                $subject = 'Xiaomi tablet';
        elseif (preg_match('/(camera|camer[aă]|selfie|foto)/u',$cap))              $subject = 'Xiaomi smartphone highlighting the camera module';
        elseif (preg_match('/(baterie|battery|charging|înc[aă]rcare|incarcare)/u',$cap))
                                                                                   $subject = 'Xiaomi smartphone with battery/charging theme';
        elseif (preg_match('/(smart home|lamp|mi home|gateway|sensor)/iu',$cap))   $subject = 'Xiaomi smart home device';

        $style = match ($theme) {
            'promo'      => 'studio product shot, clean lighting, soft shadows, minimal background',
            'educativ'   => 'lifestyle photo, natural light, person interacting with settings, cozy interior',
            default      => 'lifestyle photo, candid moment, soft daylight, friendly vibe',
        };

        $makePrompt = fn(string $sz) => "High-quality {$style} of {$subject}, portrait {$sz}. No text, no watermark, realistic, sharp focus.";

        // try primary
        $size1 = $this->normalizeSizeForModel($primary, $rawSize);
        $prompt1 = $makePrompt($size1);
        if ($debug) { $log[] = "try model=$primary size=$size1"; $log[] = 'prompt='.mb_substr($prompt1,0,140).'…'; }

        $bin = $this->callOpenAIImage($key, $primary, $prompt1, $size1, $err1);
        if ($bin) {
            $dbg = [];
            $abs = $this->saveImageToPublic($bin, 'png', $dbg);
            $log[] = "ok:$primary";
            foreach ($dbg as $m) $log[] = $m;
            $log[] = "saved=$abs";
            return [$abs, $log];
        }
        if ($err1 && $debug) $log[] = "err:$primary -> ".mb_substr($err1,0,140).'…';

        // fallback
        if ($fallback && $fallback !== $primary) {
            $size2 = $this->normalizeSizeForModel($fallback, $rawSize);
            $prompt2 = $makePrompt($size2);
            if ($debug) $log[] = "try fallback=$fallback size=$size2";

            $bin2 = $this->callOpenAIImage($key, $fallback, $prompt2, $size2, $err2);
            if ($bin2) {
                $dbg = [];
                $abs = $this->saveImageToPublic($bin2, 'png', $dbg);
                $log[] = "ok:$fallback";
                foreach ($dbg as $m) $log[] = $m;
                $log[] = "saved=$abs (fallback)";
                return [$abs, $log];
            }
            if ($err2 && $debug) $log[] = "err:$fallback -> ".mb_substr($err2,0,140).'…';
        }

        return [null, $log];
    }

    /**
     * Apelează v1/images/generations (DALL·E 3) sau (încearcă) gpt-image-1.
     * Returnează binarul imaginii sau null. Setează $err cu un mesaj dacă eșuează.
     */
    protected function callOpenAIImage(string $apiKey, string $model, string $prompt, string $size, ?string &$err): ?string
    {
        $err = null;

        // DALL·E 3 -> v1/images/generations
        if (stripos($model, 'dall-e-3') !== false) {
            try {
                $res = Http::withToken($apiKey)
                    ->timeout(60)
                    ->post('https://api.openai.com/v1/images/generations', [
                        'model' => 'dall-e-3',
                        'prompt' => $prompt,
                        'size' => $size, // 1024x1024 | 1024x1792 | 1792x1024
                        'n' => 1,
                        'response_format' => 'b64_json',
                    ]);

                if (!$res->ok()) {
                    $err = 'http '.$res->status().' '.$res->body();
                    return null;
                }

                $b64 = data_get($res->json(),'data.0.b64_json');
                return $b64 ? base64_decode($b64) : null;
            } catch (\Throwable $e) {
                $err = $e->getMessage();
                return null;
            }
        }

        // gpt-image-1 (poate cere org verificată)
        try {
            $res = Http::withToken($apiKey)
                ->timeout(60)
                ->post('https://api.openai.com/v1/images/generations', [
                    'model' => $model,
                    'prompt' => $prompt,
                    'size' => in_array($size, ['1024x1024','1024x1536','1536x1024','auto'], true) ? $size : '1024x1536',
                    'n' => 1,
                    'response_format' => 'b64_json',
                ]);

            if (!$res->ok()) {
                $err = 'http '.$res->status().' '.$res->body();
                return null;
            }

            $b64 = data_get($res->json(),'data.0.b64_json');
            return $b64 ? base64_decode($b64) : null;
        } catch (\Throwable $e) {
            $err = $e->getMessage();
            return null;
        }
    }

    /**
     * Salvează în storage/app/public/<m-Y>/ ca JPEG + thumb 400px; returnează calea ABSOLUTĂ.
     */
    protected function saveImageToPublic(string $binary, string $extIgnored, array &$debug): string
    {
        $relDir = now()->format('m-Y');
        Storage::disk('public')->makeDirectory($relDir);

        $nameBase = uniqid('img_');
        $jpgRel   = $relDir.'/'.$nameBase.'.jpg';

        $img = @imagecreatefromstring($binary);
        if ($img) {
            ob_start(); imagejpeg($img, null, 85); $jpeg = ob_get_clean(); imagedestroy($img);
            Storage::disk('public')->put($jpgRel, $jpeg);
            $srcForThumb = $jpeg;
        } else {
            // dacă nu putem decoda, punem direct
            Storage::disk('public')->put($jpgRel, $binary);
            $srcForThumb = $binary;
        }

        // thumb
        $thumbRel = $relDir.'/'.$nameBase.'-thumb.jpg';
        $thumbBin = $this->makeThumbJpeg($srcForThumb, 400);
        Storage::disk('public')->put($thumbRel, $thumbBin);

        $debug[] = "saved.rel={$jpgRel}";
        $debug[] = "thumb.rel={$thumbRel}";

        return storage_path('app/public/'.$jpgRel);
    }

    protected function makeThumbJpeg(string $binary, int $targetW = 400): string
    {
        $src = @imagecreatefromstring($binary);
        if (!$src) return $binary;

        $sw = imagesx($src); $sh = imagesy($src);
        if ($sw <= 0 || $sh <= 0) { imagedestroy($src); return $binary; }

        $tw = max(1, $targetW);
        $th = (int) round($sh * ($tw / $sw));

        $dst = imagecreatetruecolor($tw, $th);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $sw, $sh);

        ob_start(); imagejpeg($dst, null, 82); $out = ob_get_clean();
        imagedestroy($dst); imagedestroy($src);
        return $out;
    }

    protected function makePlaceholderPng(string $caption, string $theme): string
    {
        $w = 1200; $h = 1350;
        $im = imagecreatetruecolor($w, $h);

        [$c1, $c2] = match ($theme) {
            'promo'      => [[255, 84, 28], [255, 193, 7]],
            'educativ'   => [[30, 144, 255], [173, 216, 230]],
            default      => [[76, 175, 80], [200, 230, 201]],
        };

        // gradient
        for ($y=0; $y<$h; $y++) {
            $r = (int)($c1[0] + ($c2[0]-$c1[0]) * ($y/$h));
            $g = (int)($c1[1] + ($c2[1]-$c1[1]) * ($y/$h));
            $b = (int)($c1[2] + ($c2[2]-$c1[2]) * ($y/$h));
            $col = imagecolorallocate($im, $r, $g, $b);
            imageline($im, 0, $y, $w, $y, $col);
        }

        // formă albă translucidă
        $shape = imagecolorallocatealpha($im, 255, 255, 255, 70);
        imagefilledellipse($im, (int)($w*0.5), (int)($h*0.65), (int)($w*0.9), (int)($w*0.54), $shape);

        ob_start(); imagepng($im); $bin = ob_get_clean(); imagedestroy($im);
        return $bin;
    }

    /**
     * gpt-image-1: 1024x1024, 1024x1536 (portret), 1536x1024 (landscape), auto
     * dall-e-3:    1024x1024, 1024x1792 (portret), 1792x1024 (landscape)
     */
    protected function normalizeSizeForModel(string $model, ?string $want): string
    {
        $want = strtolower(trim((string)$want));
        $portrait = ['portrait','vertical','story','1024x1536','1200x1350'];
        $land     = ['landscape','horizontal','1536x1024'];
        $square   = ['square','1024x1024'];

        if (in_array($want, $portrait, true)) $want = 'portrait';
        if (in_array($want, $land, true))     $want = 'landscape';
        if (in_array($want, $square, true))   $want = '1024x1024';

        if (stripos($model, 'dall-e-3') !== false) {
            if ($want === 'portrait')   return '1024x1792';
            if ($want === 'landscape')  return '1792x1024';
            if ($want === '1024x1024')  return '1024x1024';
            if (preg_match('/^(\d+)x(\d+)$/', $want, $m)) {
                $w=(int)$m[1]; $h=(int)$m[2];
                if ($w===$h) return '1024x1024';
                return ($h>$w) ? '1024x1792' : '1792x1024';
            }
            return '1024x1792';
        }

        // gpt-image-1
        if (in_array($want, ['1024x1024','1024x1536','1536x1024','auto'], true)) return $want;
        return '1024x1536';
    }
}
