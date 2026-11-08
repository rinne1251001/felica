@extends('layouts.app')
@section('title', '生徒追加')
@section('content')

    <main class="main">
        <div class="content-wrapper">
            <form method="POST" action="{{ route('students.store') }}">
                @csrf

                <div>
                    <label for="name">生徒の名前</label>
                    <input id="name" name="name" value="{{ old('name', $student->name) }}" required>
                </div>
                <div>
                    <label for="furigana">フリガナ</label>
                    <input id="furigana" name="furigana" value="{{ old('furigana', $student->furigana) }}" required>
                </div>
                <div>
                    <label for="student_number">学籍番号</label>
                    <input id="student_number" name="student_number" value="{{ old('student_number', $student->student_number) }}" required>
                </div>
                <div>
                    <label for="is_active">照合の対象にするか</label>
                    <input type="checkbox" id="is_active" name="is_active" value="1" @checked(session()->hasOldInput() ? old('is_active') : $student->is_active)>
                </div>

                

                @if ($errors->any())
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif

                <button>保存</button>
            </form>

        </div>
    </main>

@endsection