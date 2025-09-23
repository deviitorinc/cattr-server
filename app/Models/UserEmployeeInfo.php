<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserEmployeeInfo Model
 * 
 * This model stores additional employee information for users
 * including Employee ID and Joined Date
 */
class UserEmployeeInfo extends Model
{
    use HasFactory;

    protected $table = 'user_employee_info';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'employee_id',
        'joined_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'joined_date' => 'date',
    ];

    /**
     * Get the user that owns the employee info.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
