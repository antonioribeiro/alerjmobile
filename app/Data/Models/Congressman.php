<?php

namespace App\Data\Models;

use Illuminate\Database\Eloquent\Model;

class Congressman extends Model
{
	protected $table = 'congressmen';

	protected $fillable = ['name', 'email', 'party_id', 'url', 'alerj_id', 'page'];

	public function party()
	{
		return $this->belongsTo(Party::class);
	}
}
