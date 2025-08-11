<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use Illuminate\Http\Request;

class AiSettingsController extends Controller
{
    public function edit()
    {
        $setting = AiSetting::firstOrCreate([], ['config' => []]);
        return view('admin.ai_settings', compact('setting'));
    }

    public function save(Request $request)
    {
        $setting = AiSetting::firstOrCreate([], ['config' => []]);
        $cfg = $setting->config ?? [];

        foreach (['openai_key','text_model','image_model','image_fallback','size','page_token','reply_style'] as $k) {
            $cfg[$k] = $request->input($k);
        }

        $setting->config = $cfg;
        $setting->save();

        return back()->with('ok', 'Salvat.');
    }
}
