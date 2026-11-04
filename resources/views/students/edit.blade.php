<form method="POST" action="{{ route('students.update', $student) }}">
    @csrf
    @method('PUT')

    <div>
        <label for="name">名前</label>
        <input id="name" name="name" value="{{ old('name', $student->name) }}" required>
    </div>

    <div>
        <label for="name">フリガナ</label>
        <input id="furigana" name="furigana" value="{{ old('furigana', $student->furigana) }}" required>
    </div>

    <div>
        <label for="student_number">学籍番号</label>
        <input id="student_number" name="student_number" value="{{ old('student_number', $student->student_number) }}" required>
    </div>

    <div>
        <label for="is_active">照合の対象にするか</label>
        <input id = "is_active" type="checkbox" name="is_active" value="1" @checked(session()->hasOldInput() ? old('is_active') : $student->is_active)>
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