<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FacebookClient
{
    public function publishText(string $pageId, string $pageToken, string $message): array
    {
        $res = Http::asForm()->post("https://graph.facebook.com/v23.0/{$pageId}/feed", [
            'access_token' => $pageToken,
            'message'      => $message,
        ]);

        return $res->json();
    }

    public function publishPhoto(string $pageId, string $pageToken, string $caption, string $imageUrl): array
    {
        // pentru upload din storage, dă URL public (storage:link) – Facebook cere url sau multipart
        $res = Http::asForm()->post("https://graph.facebook.com/v23.0/{$pageId}/photos", [
            'access_token' => $pageToken,
            'caption'      => $caption,
            'url'          => $imageUrl,
        ]);

        return $res->json();
    }
}