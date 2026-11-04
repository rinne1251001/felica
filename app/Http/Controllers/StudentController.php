<?php

namespace App\Http\Controllers;

// 必要なクラスや機能をインポートする
use App\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

// クラス宣言
class StudentController extends Controller
{
    // 一覧画面
    public function index(): View
    {
        // sunjectsテーブルのデータをコレクション形式で一括取得
        $students = Student::all();

        // resources/views/tests/tests/index.blade.phpのファイルを画面に表示
        return view('students.index', compact('students'));
    }

    // 新規作成画面
    public function create(): View
    {
        // まだデータベースに保存されていない中身が空のSubjectオブジェクトを生成
        // old('入力項目名')だけでなく、old('入力項目名', $subject->カラム名)と書くことができるのでcreateとeditを同じ画面にまとめられる
        return view('students.create', ['student' => new Student()]);
    }

    // 新規作成保存処理
    public function store(Request $request, ?student $student = null): RedirectResponse
    {
        // バリデーションチェック
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'furigana' => ['required', 'string', 'max:300'],
            'student_number' => ['required', 'string', 'max:20', Rule::unique('students')->ignore($student)],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        // subjectテーブルにデータを保存
        $student = Student::create($validated);

        // 処理が完了したらtests.indexにリダイレクト
        // view()はビューファイルのパスだけど、route()はルート名を書く
        return redirect()->route('students.index');
    }

    // 詳細画面
    public function show(Student $student): View
    {
        return view('students.show', compact('student'));
    }

    // 編集画面
    public function edit(Student $student): View
    {
        return view('students.edit', compact('student'));
    }

    // 編集保存処理
    public function update(Request $request, Student $student): RedirectResponse
    {
        // バリデーションチェック
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'furigana' => ['required', 'string', 'max:300'],
            'student_number' => ['required', 'string', 'max:20', Rule::unique('students')->ignore($student)],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        // subjectテーブルにデータを保存
        $student->update($validated);


        return redirect()->route('students.index');
    }

    // 削除処理
   public function destroy(Student $student): RedirectResponse
    {
        $student->delete();

        return redirect()->route('students.index');
    }
}