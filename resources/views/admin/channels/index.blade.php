@extends('layouts.app')
@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Channels</h1>
    <a class="btn btn-primary" href="{{ route('admin.channels.create') }}">Adaugă</a>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif

  <div class="table-responsive bg-white rounded shadow-sm">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Platform</th>
          <th>Page ID</th>
          <th>Nume</th>
          <th>Token (scurt)</th>
          <th>Expiră</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      @forelse($channels as $c)
        <tr>
          <td>{{ $c->id }}</td>
          <td>{{ $c->platform_label }}</td>
          <td class="text-monospace">{{ $c->page_id }}</td>
          <td>{{ $c->name }}</td>
          <td class="text-monospace">{{ $c->access_token ? Str::limit($c->access_token, 18) : '—' }}</td>
          <td>{{ $c->token_expires_at ?? '—' }}</td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.channels.edit',$c) }}">Edit</a>
            <form class="d-inline" method="post" action="{{ route('admin.channels.destroy',$c) }}">
              @csrf @method('delete')
              <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Ștergi?')">Del</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="text-center py-4 text-muted">Nimic încă.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">{{ $channels->links() }}</div>
</div>
@endsection
