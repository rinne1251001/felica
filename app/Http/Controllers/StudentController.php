<?php

namespace App\Http\Controllers;

// 必要なクラスや機能をインポートする
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

// クラス宣言
class StudentController extends Controller
{
    // 一覧画面
    public function index(): View
    {
        return view('students.index');
    }

    // 新規作成画面
    public function create(): View
    {
        //
    }

    // 新規作成保存処理
    public function store(Request $request): RedirectResponse
    {
        //
    }

    // 詳細画面
    public function show(string $id): View
    {
        //
    }

    // 編集画面
    public function edit(string $id): View
    {
        //
    }

    // 編集保存処理
    public function update(Request $request, string $id): RedirectResponse
    {
        //
    }

    // 削除処理
    public function destroy(string $id): RedirectResponse
    {
        //
    }
}