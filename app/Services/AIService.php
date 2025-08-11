<?php
namespace App\Services;
use Illuminate\Support\Facades\Storage;

class AIService
{
    public function generateCaption(string $theme): string
    {
        $fallbacks = [
            'promo' => 'Promo! Descoperă cele mai noi gadgeturi Xiaomi. Scrie-ne în privat pentru ofertă.',
            'engagement' => 'Care este funcția preferată a telefonului tău Xiaomi? Spune-ne în comentarii!',
            'educativ' => 'Știai că poți prelungi viața bateriei activând Battery Saver și optimizând aplicațiile în fundal?',
        ];
        $key = env('OPENAI_API_KEY');
        if (!$key) return $fallbacks[$theme] ?? $fallbacks['engagement'];

        try {
            $ai = \OpenAI::client($key);
            $res = $ai->chat()->create([
                'model' => env('AI_MODEL_TEXT','gpt-4o-mini'),
                'messages' => [
                    ['role'=>'system','content'=>'Scrii postări Facebook scurte, în română, friendly.'],
                    ['role'=>'user','content'=>"Tema: {$theme}. 2-3 fraze, friendly, fără emoji excesive."],
                ],
                'temperature'=>0.8,
            ]);
            return trim($res->choices[0]->message->content ?? $fallbacks[$theme]);
        } catch (\Throwable $e) { report($e); return $fallbacks[$theme]; }
    }

    public function generateImageFromCaption(string $caption, string $theme, bool $debug=false): array
    {
        $key  = env('OPENAI_API_KEY');
        $size = match (strtolower(env('AI_IMAGE_SIZE','portrait'))) {
            'square' => '1024x1024',
            'landscape' => '1536x1024',
            default => '1024x1536',
        };

        if ($key) {
            try {
                $ai = \OpenAI::client($key);
                $model  = env('AI_MODEL_IMAGE','gpt-image-1');
                $prompt = "High-quality lifestyle photo of a modern Xiaomi smartphone, soft daylight, friendly vibe. No text. {$size}.";
                try {
                    $r = $ai->images()->create(['model'=>$model,'prompt'=>$prompt,'size'=>$size]);
                } catch (\Throwable $e) {
                    $r = $ai->images()->create(['model'=>env('AI_MODEL_IMAGE_FALLBACK','dall-e-3'),'prompt'=>$prompt,'size'=>'1024x1024']);
                }
                $b64 = $r->data[0]->b64_json ?? null;
                if ($b64) {
                    $raw = base64_decode($b64);
                    $rel = 'media/'.now()->format('Y/m').'/'.uniqid('img_').'.jpg';
                    Storage::disk('public')->put($rel, $raw);
                    return [storage_path('app/public/'.$rel), ['rel'=>$rel]];
                }
            } catch (\Throwable $e) { report($e); }
        }

        // placeholder local
        $rel = 'media/'.now()->format('Y/m').'/'.uniqid('ph_').'.jpg';
        $abs = storage_path('app/public/'.$rel);
        if (!is_dir(dirname($abs))) mkdir(dirname($abs), 0775, true);
        $im = imagecreatetruecolor(1024,1024);
        $bg = imagecolorallocate($im,230,245,235);
        imagefilledrectangle($im,0,0,1024,1024,$bg);
        imagejpeg($im,$abs,90); imagedestroy($im);
        return [$abs, ['rel'=>$rel,'placeholder'=>true]];
    }
}
