@extends('layouts.app')

@section('content')
<div class="container py-4">
  <h1 class="h4 mb-3">Comentarii procesate</h1>

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif

  <div class="table-responsive bg-white rounded shadow-sm">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Page ID</th>
        <th>Post ID</th>
        <th>Comment ID</th>
        <th>Mesaj</th>
        <th>Răspuns</th>
        <th>Status</th>
        <th>Creat</th>
      </tr>
      </thead>
      <tbody>
      @forelse($logs as $log)
        <tr>
          <td>{{ $log->id }}</td>
          <td class="text-muted">{{ $log->page_id }}</td>
          <td class="text-muted">{{ $log->post_id }}</td>
          <td class="text-muted">{{ $log->comment_id }}</td>
          <td style="max-width: 320px; white-space: normal;">{{ $log->message }}</td>
          <td style="max-width: 320px; white-space: normal;">{{ $log->reply }}</td>
          <td>
            @php $badge = [
                'sent' => 'success',
                'skipped' => 'secondary',
                'error' => 'danger',
                'hidden' => 'warning',
            ][$log->status] ?? 'secondary'; @endphp

            <span class="badge bg-{{ $badge }}">{{ $log->status ?? 'n/a' }}</span>
          </td>
          <td class="text-nowrap">{{ $log->created_at }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="8" class="text-center py-4 text-muted">Nu sunt loguri încă.</td>
        </tr>
      @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">
    {{ $logs->links() }}
  </div>
</div>
@endsection
