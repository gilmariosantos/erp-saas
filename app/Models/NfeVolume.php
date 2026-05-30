<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NfeVolume extends Model
{
    protected $fillable = ['nfe_id','quantidade','especie','marca','numeracao','peso_liquido','peso_bruto','lacres'];
    protected function casts(): array { return ['lacres' => 'array']; }
    public function nfe() { return $this->belongsTo(Nfe::class); }
}
