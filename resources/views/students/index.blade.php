@extends('layouts.app')
@section('title', '生徒一覧')
@push('styles')
    <style>
        .table-container {
            overflow-x: auto;
            max-width: 100%;
        }
        table {
            border-collapse: collapse;
        }
        th {
            background-color: var(--color-main);
            color: var(--color-bg);
            border: 1px solid var(--color-bg);
            padding-block: 7px;
        }
        td {
            color: var(--color-main);
            border: 1px solid var(--color-main);
            padding: 10px 20px;
        }
    </style>
@endpush

@section('content')

    <main class="main">
        <div class="content-wrapper">

            <div>
                <p>全{{ $students->count() }}件</p>
                <p><a href="{{ route('students.create') }}">生徒追加</a></p>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr><th>名前</th><th></th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($students as $student)
                            <tr>
                                <td><a href="{{ route('students.show', $student) }}">{{ $student->name }}</a></td>
                                <td><a href="{{ route('students.edit', $student) }}" class="btn">編集</a></td>
                                <td><form method="POST" action="{{ route('students.destroy', $student) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn">この生徒を削除</button>
                                </form></td>
                            </tr>
                        @empty
                            <tr colspan="3"><td>いません</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </main>

@endsection
@push('scripts')
    <script></script>
@endpush