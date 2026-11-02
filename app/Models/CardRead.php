<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 入校証をタッチした記録
class CardRead extends Model
{
    public const UPDATED_AT = null; // タッチした時刻（created_at）だけ使う

    protected $fillable = ['student_card_id', 'student_id'];

    // タッチしたカード
    public function card(): BelongsTo
    {
        return $this->belongsTo(StudentCard::class, 'student_card_id');
    }

    // タッチした時点でカードを持っていた生徒
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    // タッチされた IDm を記録する（はじめてのカードなら、誰のものでもないカードとして登録する）
    // $idm は、先にバリデーションで「16桁の16進数」か確かめておく
    public static function record(string $idm): self
    {
        $card = StudentCard::firstOrCreate(['idm' => StudentCard::normalizeIdm($idm)]);

        return $card->reads()->create(['student_id' => $card->student_id]);
    }
}