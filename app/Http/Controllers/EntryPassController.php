<?php

namespace App\Http\Controllers;

use App\Models\CardRead;
use App\Models\Student;
use App\Models\StudentCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// 入校証（FeliCa）の照合・登録
class EntryPassController extends Controller
{
    // IDm は 8 バイト = 16 桁の 16 進数
    private const IDM_RULE = ['required', 'string', 'regex:/^[0-9A-Fa-f]{16}$/'];

    private const IDM_MESSAGES = [
        'idm.required' => 'IDm が送信されていません。',
        'idm.regex'    => 'IDm の形式が不正です（16桁の16進数である必要があります）。',
    ];

    // 照合・登録画面
    public function index(): View
    {
        return view('entry_pass.index');
    }

    // タッチされた入校証を照合する（はじめてのカードは、誰のものでもないカードとして自動で登録する）
    public function touch(Request $request): JsonResponse
    {
        $validated = $request->validate(['idm' => self::IDM_RULE], self::IDM_MESSAGES);
        $idm = StudentCard::normalizeIdm($validated['idm']);

        $isNew = ! StudentCard::where('idm', $idm)->exists();

        // タッチの記録を残す（はじめてのカードなら、ここでカードも登録される）
        $read = CardRead::record($idm);
        $card = $read->card()->with('student')->first();

        if (! $card->is_active) {
            return response()->json([
                'status'  => 'inactive',
                'idm'     => $idm,
                'message' => '使用停止中の入校証です。',
            ]);
        }

        if ($card->student === null) {
            return response()->json([
                'status'  => 'unlinked',
                'idm'     => $idm,
                'is_new'  => $isNew,
                'message' => $isNew
                    ? "新しい入校証を登録しました（IDm {$idm}）。"
                    : "生徒が登録されていない入校証です（IDm {$idm}）。",
            ]);
        }

        if (! $card->student->is_active) {
            return response()->json([
                'status'  => 'inactive',
                'idm'     => $idm,
                'message' => '照合の対象外になっている生徒の入校証です。',
            ]);
        }

        return response()->json([
            'status'  => 'linked',
            'idm'     => $idm,
            'student' => $this->studentData($card->student),
        ]);
    }

    // 名前・ふりがな・学籍番号で生徒を探す
    // 生徒の名前を URL やアクセスログに残さないよう、検索語は POST の本文で受け取る
    public function searchStudents(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'max:50']]);
        $keyword = trim($validated['q']);

        // ふりがなは「ひらがな」「カタカナ」どちらで保存されていても見つかるようにする
        // （% と _ はワイルドカードとして扱われるが、検索結果が広がるだけなので問題ない）
        $hiragana = mb_convert_kana($keyword, 'cHV');
        $katakana = mb_convert_kana($keyword, 'CKV');

        $students = Student::query()
            ->where('is_active', true)
            ->where(function ($query) use ($keyword, $hiragana, $katakana) {
                $query->where('name', 'like', "%{$keyword}%")
                    ->orWhere('furigana', 'like', "%{$hiragana}%")
                    ->orWhere('furigana', 'like', "%{$katakana}%")
                    ->orWhere('student_number', 'like', "{$keyword}%");
            })
            ->orderBy('student_number')
            ->limit(20)
            ->get();

        return response()->json([
            'students' => $students->map(fn (Student $student) => $this->studentData($student))->values(),
        ]);
    }

    // 生徒が決まっていない入校証に、生徒を登録する
    public function link(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idm'        => self::IDM_RULE,
            'student_id' => ['required', 'integer', 'exists:students,id'],
        ], self::IDM_MESSAGES + [
            'student_id.exists' => '選んだ生徒が見つかりません。',
        ]);

        $idm = StudentCard::normalizeIdm($validated['idm']);
        $card = StudentCard::where('idm', $idm)->first();

        if ($card === null) {
            return response()->json(['message' => 'この入校証はまだ登録されていません。もう一度タッチしてください。'], 404);
        }
        if (! $card->is_active) {
            return response()->json(['message' => '使用停止中の入校証には生徒を登録できません。'], 422);
        }

        $student = Student::find($validated['student_id']);
        if (! $student->is_active) {
            return response()->json(['message' => '照合の対象外になっている生徒は登録できません。'], 422);
        }

        $linked = DB::transaction(function () use ($card, $student) {
            // 生徒が決まっていない場合だけ書き換える（同時に2人が登録しようとしても、先に登録した方だけが残る）
            $updated = StudentCard::whereKey($card->id)
                ->whereNull('student_id')
                ->update(['student_id' => $student->id]);

            if ($updated === 0) {
                return false;
            }

            // 登録の直前（5分以内）にこのカードをタッチした記録にも、生徒を付けておく
            $card->reads()
                ->whereNull('student_id')
                ->where('created_at', '>=', now()->subMinutes(5))
                ->update(['student_id' => $student->id]);

            return true;
        });

        if (! $linked) {
            return response()->json(['message' => 'この入校証には、すでに別の生徒が登録されています。'], 409);
        }

        return response()->json([
            'status'  => 'linked',
            'idm'     => $idm,
            'student' => $this->studentData($student),
            'message' => "{$student->name}（{$student->student_number}）さんの入校証として登録しました。",
        ]);
    }

    // 画面に返す生徒の情報（必要な項目だけ）
    private function studentData(Student $student): array
    {
        return [
            'id'             => $student->id,
            'name'           => $student->name,
            'student_number' => $student->student_number,
        ];
    }
}