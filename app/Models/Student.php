<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = ['name', 'furigana', 'student_number', 'school_class_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function cards(): HasMany
    {
        return $this->hasMany(StudentCard::class);
    }

    // 入校証をタッチした記録
    public function cardReads(): HasMany
    {
        return $this->hasMany(CardRead::class);
    }
}
