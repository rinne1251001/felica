<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class FelicaController extends Controller
{
    /**
     * IDm 読み取り画面を表示する。
     */
    public function index(): View
    {
        return view('felica.index');
    }

    /**
     * ブラウザ（WebUSB）から送られてきた IDm と学籍番号を受け取り処理する。
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                // IDm は 8 バイト = 16 桁の 16 進数
                'idm'        => ['required', 'string', 'regex:/^[0-9A-Fa-f]{16}$/'],
                // 学籍番号はカードの形式が確定するまで緩めに受け取る（読めなかった場合は null）
                'student_id' => ['nullable', 'string', 'max:64'],
            ],
            [
                'idm.required'   => 'IDm が送信されていません。',
                'idm.regex'      => 'IDm の形式が不正です（16桁の16進数である必要があります）。',
                'student_id.max' => '学籍番号が長すぎます。',
            ],
        );

        $idm = strtoupper($validated['idm']);
        $studentId = $validated['student_id'] ?? null;

        // ------------------------------------------------------------------
        // ここに業務処理を書く（例：出席・入退室記録、学生マスタとの照合など）
        //
        // $student = Student::where('student_number', $studentId)->first();
        // if (! $student) {
        //     return response()->json([
        //         'status'  => 'unknown_student',
        //         'idm'     => $idm,
        //         'message' => '登録されていない学籍番号です。',
        //     ], 404);
        // }
        // Attendance::create(['student_id' => $student->id, 'touched_at' => now()]);
        // ------------------------------------------------------------------

        Log::info('FeliCa card received', [
            'idm'        => $idm,
            'student_id' => $studentId,
            'ip'         => $request->ip(),
        ]);

        return response()->json([
            'status'      => 'ok',
            'idm'         => $idm,
            'student_id'  => $studentId,
            'received_at' => now()->toIso8601String(),
            'message'     => $studentId !== null
                ? "学籍番号 {$studentId}（IDm {$idm}）を受け付けました。"
                : "IDm {$idm} を受け付けました。",
        ]);
    }
}