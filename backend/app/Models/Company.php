<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasUuids;

    protected $fillable = ['name'];

    public function executives(): HasMany
    {
        return $this->hasMany(Executive::class, 'fk_company_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'fk_company_id');
    }

    public function knowledgeBases(): HasMany
    {
        return $this->hasMany(KnowledgeBase::class, 'fk_company_id');
    }
}
