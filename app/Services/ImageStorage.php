<?php
namespace App\Services;
use Illuminate\Support\Facades\Storage;
use App\Models\Medium;
use Illuminate\Support\Str;

class ImageStorage
{
    public static function storeLocalJpeg(string $absPath): int
    {
        $data = file_get_contents($absPath);
        $rel = 'media/'.now()->format('Y/m').'/'.Str::uuid().'.jpg';
        Storage::disk('public')->put($rel, $data);
        $m = Medium::create([
            'uuid'=>Str::uuid(),'disk'=>'public','path'=>$rel,'mime'=>'image/jpeg','size'=>strlen($data),'conversions'=>null
        ]);
        return (int)$m->id;
    }
}
