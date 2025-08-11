@extends('layouts.app')

@section('content')
<div class="container py-4">
  <h1 class="h4 mb-3">{{ $post->exists ? 'Editează' : 'Adaugă' }} post</h1>

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  {{-- FORMULARUL DE SALVARE (PUT pentru edit, POST pentru create) --}}
  <form id="form-save" method="post" enctype="multipart/form-data"
        action="{{ $post->exists ? route('admin.posts.update',$post) : route('admin.posts.store') }}">
    @csrf
    @if($post->exists)
      @method('PUT')
    @endif

    <div class="mb-3">
      <label class="form-label">Titlu (opțional)</label>
      <input name="title" class="form-control" value="{{ old('title',$post->title) }}">
    </div>

    <div class="mb-3">
      <label class="form-label">Conținut</label>
      <textarea name="body" class="form-control" rows="6">{{ old('body',$post->body) }}</textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Imagine (opțional)</label>
      <input type="file" name="image" class="form-control">
      @if($post->exists && $post->media->count())
        <div class="mt-2 d-flex gap-3 flex-wrap">
          @foreach($post->media as $m)
            <div><img src="{{ Storage::disk($m->disk)->url($m->path) }}" style="max-height:120px" alt="media"></div>
          @endforeach
        </div>
      @endif
    </div>

    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          @foreach(['draft','scheduled','published'] as $s)
            <option value="{{ $s }}" @selected(old('status',$post->status)==$s)>{{ ucfirst($s) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Programare</label>
        <input type="datetime-local" name="scheduled_at" class="form-control"
               value="{{ old('scheduled_at', optional($post->scheduled_at)->format('Y-m-d\TH:i')) }}">
      </div>
    </div>

    <div class="mb-3 mt-3">
      <label class="form-label">Distribuie pe canale</label>
      <div class="row">
        @foreach($channels as $c)
          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="channel_ids[]"
                     value="{{ $c->id }}" {{ in_array($c->id, $selected ?? []) ? 'checked' : '' }}>
              <span class="form-check-label">{{ $c->platform_label }} — {{ $c->name ?? $c->page_id }}</span>
            </label>
          </div>
        @endforeach
      </div>
    </div>

    <div class="mt-3 d-flex gap-2">
      <button class="btn btn-primary" type="submit">Salvează</button>

      {{-- FORMULAR SEPARAT pentru PUBLICARE ACUM (POST) --}}
      @if($post->exists)
        <form method="POST" action="{{ route('admin.posts.publish',$post) }}" class="d-inline">
          @csrf
          <button type="submit" class="btn btn-success"
                  onclick="return confirm('Publicăm acum?')">Publică acum</button>
        </form>
      @endif

      <a href="{{ route('admin.posts.index') }}" class="btn btn-link">Înapoi</a>
    </div>
  </form>
</div>
@endsection
