@extends('layouts.app')
@section('title', '生徒一覧')
@push('styles')
    <style>

    </style>
@endpush

@section('content')

    <main class="main">
        <div class="content-wrapper">

            {{ $students->count() }}件

            @forelse($students as $student)
                <a href="{{ route('students.show', $student) }}">{{ $student->name }}</a>
                <a href="{{ route('students.edit', $student) }}">編集</a><br>
                <form method="POST" action="{{ route('students.destroy', $student) }}">
                    @csrf
                    @method('DELETE')
                    <button>この生徒を削除</button>
                </form>
            @empty
                いません
            @endforelse

        </div>
    </main>

@endsection
@push('scripts')
    <script></script>
@endpush