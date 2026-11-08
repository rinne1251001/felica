<?php

namespace Database\Seeders;

use App\Models\Student;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// テスト用の生徒を 200 人登録する
// 実行: php artisan db:seed --class=StudentSeeder
class StudentSeeder extends Seeder
{
    private const COUNT = 200;

    public function run(): void
    {
        DB::transaction(function () {
            // 何度実行しても同じ 200 人になるよう、前回入れたテスト用の生徒（学籍番号が T で始まる）を消してから入れ直す
            // （その生徒に紐づけた入校証は消えずに、「誰のものでもないカード」に戻る）
            Student::where('student_number', 'like', 'T%')->delete();

            StudentFactory::new()
                ->count(self::COUNT)
                ->sequence(fn (Sequence $sequence) => [
                    // 学籍番号: T + 入学年度の下2桁（24〜26） + 連番3桁　例: T24001, T25001, T26001, T24002 …
                    'student_number' => sprintf('T%02d%03d', 24 + $sequence->index % 3, intdiv($sequence->index, 3) + 1),
                    // 20 人に 1 人は「照合の対象外」にして、その表示も確認できるようにする
                    'is_active'      => $sequence->index % 20 !== 19,
                ])
                ->create();
        });

        $this->command?->info(self::COUNT.' 人のテスト用の生徒を登録しました（学籍番号は T で始まります）。');
    }
}