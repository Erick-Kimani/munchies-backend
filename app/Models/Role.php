<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    // These three IDs are hardcoded elsewhere in the app (User::isAdmin/
    // isSeller/isUser, PropertySubmissionController's seller-promotion
    // logic) rather than looked up dynamically — so renaming, deleting,
    // or otherwise mutating one of these rows would silently break those
    // checks without touching a single line of PHP. RoleController uses
    // these constants to block exactly that. If the app ever needs more
    // than three roles, give the new ones IDs above 3 and leave these
    // alone.
    public const ADMIN = 1;
    public const SELLER = 2;
    public const USER = 3;

    public const CORE_ROLE_IDS = [self::ADMIN, self::SELLER, self::USER];

    public static function isCoreRoleId(int $id): bool
    {
        return in_array($id, self::CORE_ROLE_IDS, true);
    }
}
