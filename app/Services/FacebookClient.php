<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;

class FacebookClient
{
    public function __construct(private string $pageToken) {}

    public function getPageId(): ?string
    {
        $r = Http::timeout(20)->get('https://graph.facebook.com/v23.0/me', [
            'access_token'=>$this->pageToken, 'fields'=>'id,name'
        ]);
        return $r->successful() ? ($r->json('id') ?? null) : null;
    }

    public function listPosts(string $pageId, array $params=[]): array
    {
        $qs = array_merge(['fields'=>'id,created_time,message,permalink_url','limit'=>25], $params, ['access_token'=>$this->pageToken]);
        $r = Http::timeout(25)->get("https://graph.facebook.com/v23.0/{$pageId}/posts", $qs);
        return $r->successful() ? ($r->json('data') ?? []) : [];
    }

    public function publishText(string $pageId, string $caption): ?string
    {
        $r = Http::timeout(25)->asForm()->post("https://graph.facebook.com/v23.0/{$pageId}/feed", [
            'message'=>$caption, 'access_token'=>$this->pageToken
        ]);
        return $r->successful() ? ($r->json('id') ?? null) : null;
    }

    public function publishPhoto(string $pageId, string $caption, string $imagePath): ?string
    {
        $r1 = Http::timeout(30)
            ->attach('source', file_get_contents($imagePath), basename($imagePath))
            ->asMultipart()
            ->post("https://graph.facebook.com/v23.0/{$pageId}/photos", [
                'published'=>'false','access_token'=>$this->pageToken
            ]);
        if (!$r1->successful()) return null;
        $photoId = $r1->json('id') ?? null;
        if (!$photoId) return null;

        $r2 = Http::timeout(25)->asForm()->post("https://graph.facebook.com/v23.0/{$pageId}/feed", [
            'message'=>$caption,
            'attached_media[0]' => json_encode(['media_fbid'=>$photoId]),
            'access_token'=>$this->pageToken,
        ]);
        return $r2->successful() ? ($r2->json('id') ?? null) : null;
    }

    public function getComments(string $postId, array $params=[]): array
    {
        $qs = array_merge(['fields'=>'id,from,message,created_time','filter'=>'stream','limit'=>50'], $params, ['access_token'=>$this->pageToken]);
        $r = Http::timeout(25)->get("https://graph.facebook.com/v23.0/{$postId}/comments", $qs);
        return $r->successful() ? ($r->json('data') ?? []) : [];
    }

    public function replyToComment(string $commentId, string $message): ?string
    {
        $r = Http::timeout(20)->asForm()->post("https://graph.facebook.com/v23.0/{$commentId}/comments", [
            'message'=>$message,'access_token'=>$this->pageToken
        ]);
        return $r->successful() ? ($r->json('id') ?? null) : null;
    }
}
