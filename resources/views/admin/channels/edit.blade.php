@extends('layouts.app')
@section('content')
<div class="container py-4">
  <h1 class="h4 mb-3">{{ $channel->exists ? 'Editează channel' : 'Adaugă channel' }}</h1>

  <form method="post" action="{{ $channel->exists ? route('admin.channels.update',$channel) : route('admin.channels.store') }}">
    @csrf
    @if($channel->exists) @method('put') @endif

    <div class="row g-3">
      <div class="col-md-2">
        <label class="form-label">Platform</label>
        <select name="platform" class="form-select">
          <option value="fb" @selected(old('platform',$channel->platform)=='fb')>Facebook</option>
          <option value="ig" @selected(old('platform',$channel->platform)=='ig')>Instagram</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Page ID</label>
        <input name="page_id" class="form-control" value="{{ old('page_id',$channel->page_id) }}" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Nume (optional)</label>
        <input name="name" class="form-control" value="{{ old('name',$channel->name) }}">
      </div>
      <div class="col-12">
        <label class="form-label">Access Token (Page Token)</label>
        <input name="access_token" class="form-control" value="{{ old('access_token',$channel->access_token) }}">
        <div class="form-text">Pentru MVP poți lipi tokenul aici. Ulterior adăugăm OAuth + criptare.</div>
      </div>
      <div class="col-md-4">
        <label class="form-label">Expiră la</label>
        <input name="token_expires_at" type="datetime-local" class="form-control"
               value="{{ old('token_expires_at', optional($channel->token_expires_at)->format('Y-m-d\TH:i')) }}">
      </div>
    </div>

    <button class="btn btn-primary mt-3">Salvează</button>
    <a class="btn btn-link mt-3" href="{{ route('admin.channels.index') }}">Înapoi</a>
  </form>
</div>
@endsection
