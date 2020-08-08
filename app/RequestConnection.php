<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RequestConnection extends Model
{
    protected $fillable = ['after_rev', 'before_rev'];

    public function afterRev()
    {
        return $this->hasOne(Request::class, 'id', 'after_rev');
    }

    public function beforeRev()
    {
        return $this->hasOne(Request::class, 'id', 'before_rev');
    }
}
