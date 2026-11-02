<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 入校証（FeliCa）。生徒が決まる前のカードも保存できるように student_id は null 可
        Schema::create('student_cards', function (Blueprint $table) {
            $table->id();
            // 生徒を削除したら、カードは消さずに「誰のものでもない」状態に戻す
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->char('idm', 16)->unique();           // 16桁の16進数。同じカードは1枚だけ
            $table->boolean('is_active')->default(true); // 紛失したら false
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_cards');
    }
};