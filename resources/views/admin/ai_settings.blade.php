@extends('layouts.app')
@section('content')
<div class="container py-4">
    @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
    <h1 class="h4 mb-3">AI Bot – Setări</h1>
    <form method="post">@csrf
        <div class="mb-3"><label>OpenAI API Key</label>
            <input name="openai_key" class="form-control" value="{{ data_get($setting->config,'openai_key') }}">
        </div>
        <div class="row">
            <div class="col"><label>Text model</label>
                <input name="text_model" class="form-control" value="{{ data_get($setting->config,'text_model','gpt-4o-mini') }}">
            </div>
            <div class="col"><label>Image model</label>
                <input name="image_model" class="form-control" value="{{ data_get($setting->config,'image_model','gpt-image-1') }}">
            </div>
        </div>
        <div class="mt-3"><label>Facebook Page Access Token</label>
            <input name="page_token" class="form-control" value="{{ data_get($setting->config,'page_token') }}">
        </div>
        <div class="mt-3"><label>Ton răspuns</label>
            <input name="reply_style" class="form-control" value="{{ data_get($setting->config,'reply_style','prietensc, concis, politicos') }}">
        </div>
        <button class="btn btn-primary mt-3">Salvează</button>
    </form>
</div>
@endsection
