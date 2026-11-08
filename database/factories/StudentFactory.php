<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * テスト用の生徒
 *
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    // Student モデルに HasFactory を付けなくても使えるように、対象のモデルをここで指定する
    protected $model = Student::class;

    // 名字と名前（読みはひらがな）。組み合わせて「名前」と「ふりがな」を作るので、漢字と読みが必ず一致する
    private const LAST_NAMES = [
        '佐藤' => 'さとう', '鈴木' => 'すずき', '高橋' => 'たかはし', '田中' => 'たなか', '伊藤' => 'いとう',
        '渡辺' => 'わたなべ', '山本' => 'やまもと', '中村' => 'なかむら', '小林' => 'こばやし', '加藤' => 'かとう',
        '吉田' => 'よしだ', '山田' => 'やまだ', '佐々木' => 'ささき', '山口' => 'やまぐち', '松本' => 'まつもと',
        '井上' => 'いのうえ', '木村' => 'きむら', '林' => 'はやし', '斎藤' => 'さいとう', '清水' => 'しみず',
        '山崎' => 'やまざき', '森' => 'もり', '池田' => 'いけだ', '橋本' => 'はしもと', '阿部' => 'あべ',
        '石川' => 'いしかわ', '山下' => 'やました', '中島' => 'なかじま', '石井' => 'いしい', '小川' => 'おがわ',
    ];

    private const FIRST_NAMES = [
        '大翔' => 'ひろと', '蓮' => 'れん', '悠真' => 'ゆうま', '湊' => 'みなと', '陽翔' => 'はると',
        '樹' => 'いつき', '颯太' => 'そうた', '悠人' => 'ゆうと', '陸' => 'りく', '翔太' => 'しょうた',
        '健太' => 'けんた', '拓海' => 'たくみ', '大和' => 'やまと', '海斗' => 'かいと', '駿' => 'しゅん',
        '陽菜' => 'ひな', '結菜' => 'ゆいな', '葵' => 'あおい', '凛' => 'りん', '芽依' => 'めい',
        'さくら' => 'さくら', '美咲' => 'みさき', '結衣' => 'ゆい', '心春' => 'こはる', '紬' => 'つむぎ',
        '莉子' => 'りこ', '美羽' => 'みう', '杏' => 'あん', '楓' => 'かえで', '七海' => 'ななみ',
    ];

    public function definition(): array
    {
        // 同姓同名ができないよう、名字と名前の組み合わせ（30 × 30 = 900 通り）から重複なしで選ぶ
        [$last, $first] = fake()->unique()->randomElement(self::nameCombinations());

        return [
            'name'           => "{$last} {$first}",
            'furigana'       => self::LAST_NAMES[$last].' '.self::FIRST_NAMES[$first],
            // テスト用と分かるように「T」で始める（StudentSeeder では連番で上書きする）
            'student_number' => fake()->unique()->numerify('T######'),
            'is_active'      => true,
        ];
    }

    // 照合の対象外にした生徒
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /** @return list<array{0: string, 1: string}> 名字と名前のすべての組み合わせ */
    private static function nameCombinations(): array
    {
        static $combinations = null;

        return $combinations ??= collect(array_keys(self::LAST_NAMES))
            ->crossJoin(array_keys(self::FIRST_NAMES))
            ->all();
    }
}