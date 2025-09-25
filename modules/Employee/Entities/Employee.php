<?php

namespace Modules\Employee\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'employees';
    
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'employee_id',
        'date_of_joined',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'date_of_joined' => 'date',
    ];

    /**
     * Get the user that owns the employee record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
