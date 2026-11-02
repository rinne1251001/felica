<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 入校証をタッチした記録（タッチするたびに1行増える。消さずに残す）
        Schema::create('card_reads', function (Blueprint $table) {
            $table->id();
            // どのカードか（カードを削除したら記録も消える。ふつうは削除せず is_active を false にする）
            $table->foreignId('student_card_id')->constrained()->cascadeOnDelete();
            // タッチした時点でカードを持っていた生徒（あとでカードを付け替えても、記録は当時の生徒のまま）
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent(); // タッチした時刻
            $table->index(['student_id', 'created_at']);  // 「この生徒がいつタッチしたか」を探しやすくする
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_reads');
    }
};