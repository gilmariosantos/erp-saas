<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NfeCobranca extends Model
{
    use HasFactory;
    protected $fillable = ['nfe_id','numero_duplicata','vencimento','valor'];
    protected function casts(): array { return ['vencimento' => 'date','valor' => 'decimal:2']; }
    public function nfe() { return $this->belongsTo(Nfe::class); }
}
