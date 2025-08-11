@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Posts</h1>
    <a class="btn btn-primary" href="{{ route('admin.posts.create') }}">Adaugă</a>
  </div>

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif

  <div class="table-responsive bg-white rounded shadow-sm">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Titlu</th>
          <th>Status</th>
          <th>Programare</th>
          <th>Publicat</th>
          <th class="text-end">Acțiuni</th>
        </tr>
      </thead>
      <tbody>
      @forelse($posts as $p)
        <tr>
          <td>{{ $p->id }}</td>
          <td>{{ $p->title ?: \Illuminate\Support\Str::limit(strip_tags($p->body), 60) }}</td>
          <td>{{ ucfirst($p->status) }}</td>
          <td>{{ $p->scheduled_at ? $p->scheduled_at : '—' }}</td>
          <td>{{ $p->published_at ? $p->published_at : '—' }}</td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.posts.edit',$p) }}">Edit</a>

            {{-- Publică acum: formular mic pe POST --}}
            @if($p->status !== 'published')
              <form method="POST" action="{{ route('admin.posts.publish',$p) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-success"
                        onclick="return confirm('Publicăm acum postarea #{{ $p->id }} ?')">
                  Publish now
                </button>
              </form>
            @endif

            {{-- (opțional) Ștergere --}}
            <form method="POST" action="{{ route('admin.posts.destroy',$p) }}" class="d-inline"
                  onsubmit="return confirm('Ștergi postarea #{{ $p->id }} ?')">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-sm btn-outline-danger">Del</button>
            </form>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="text-center py-4 text-muted">Nimic încă.</td>
        </tr>
      @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">
    {{ $posts->links() }}
  </div>
</div>
@endsection
