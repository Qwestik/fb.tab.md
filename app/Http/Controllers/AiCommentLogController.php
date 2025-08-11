<?php

namespace App\Http\Controllers;

use App\Models\CommentLog;
use Illuminate\View\View;

class AiCommentLogController extends Controller
{
    public function index(): View
    {
        $logs = CommentLog::orderByDesc('created_at')->paginate(50);
        return view('admin.ai_comments', compact('logs'));
    }
}
