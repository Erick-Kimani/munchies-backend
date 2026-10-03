<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    // Create Role
    public function saveRole(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'slug' => 'required|string|unique:roles,slug'
        ]);

            $role = new Role();
            $role->name = $request->name;
            $role->slug= $request->slug;

        try {
            $role->save();
            return response()->json($role);

        } catch (\Exception $error) {
            return response()->json([
                "Error" => "Failed to create a role.",
                "Message" => $error->getMessage()
            ], 500);
        }
    }

    // Fetch all Roles
    public function fetchRoles()
    {
        try {
            $roles = Role::all();
            return response()->json($roles);

        } catch (\Exception $error) {
            return response()->json([
                "Error" => "Failed to fetch roles.",
                "Message" => $error->getMessage()
            ], 500);
        }
    }

    // Fetch a specific Role
    public function fetchRole($id)
    {
        try {
            $role = Role::findOrFail($id);
            return response()->json($role);

        } catch (ModelNotFoundException $error) {
            return response()->json([
                "Error" => "Role not found.",
            ], 404);
        } catch (\Exception $error) {
            return response()->json([
                "Error" => "Failed to fetch role.",
                "Message" => $error->getMessage()
            ], 500);
        }
    }

    // Update Role
    //
    // SECURITY / DATA INTEGRITY: the three core roles (Administrator,
    // Seller, User — see Role::CORE_ROLE_IDS) are hardcoded by ID
    // throughout the app (User::isAdmin/isSeller/isUser check
    // role_id === 1/2/3 directly; PropertySubmissionController promotes
    // role_id 3 -> 2). Renaming or re-slugging one of these — even just
    // role 1's `slug` from "administrator" to something else — would
    // silently break the FRONTEND's role.slug === 'administrator' checks
    // (see router/index.js, Navbar.vue, Footer.vue) while the BACKEND'S
    // id-based checks kept working, locking real admins out of the UI
    // without any backend error. So core roles are blocked from being
    // edited here entirely; only additional, non-core roles can be
    // renamed.
    public function updateRole($id, Request $request)
    {
        if (Role::isCoreRoleId((int) $id)) {
            return response()->json([
                "Error" => "This is a core system role and cannot be modified.",
            ], 422);
        }

        try {
            $role = Role::findOrFail($id);

            // `slug` was previously `nullable`, so an update with no slug
            // sent (e.g. a form that only edits `name`) would overwrite it
            // with NULL — and since the `slug` column is NOT NULL, that
            // threw a raw DB exception caught below and returned as a 500
            // with the SQL error message exposed to the client. Requiring
            // it here (and excluding this row's own current slug from the
            // uniqueness check, so re-saving without changing the slug
            // still passes) avoids both problems.
            $request->validate([
                'name' => 'required|string',
                'slug' => ['required', 'string', 'max:1000', Rule::unique('roles', 'slug')->ignore($role->id)],
           ]);

            $role->name = $request->name;
            $role->slug= $request->slug;
            $role->save();

            return response()->json($role);

        } catch (ModelNotFoundException $error) {
            return response()->json([
                "Error" => "Role not found.",
            ], 404);
        } catch (\Exception $error) {
            return response()->json([
                "Error" => "Failed to update role.",
                "Message" => $error->getMessage()
            ], 500);
        }
    }

    // Delete Role
    //
    // Same reasoning as updateRole(): deleting a core role would also
    // orphan every user currently assigned to it (role_id foreign key)
    // and break the hardcoded id checks throughout the app, so it's
    // blocked here regardless of whether any users currently hold it.
    public function deleteRole($id)
    {
        if (Role::isCoreRoleId((int) $id)) {
            return response()->json([
                "Error" => "This is a core system role and cannot be deleted.",
            ], 422);
        }

        try {
            $role = Role::findOrFail($id);
            $role->delete();

            return response()->json("Role Deleted Successfully");

        } catch (ModelNotFoundException $error) {
            return response()->json([
                "Error" => "Role not found.",
            ], 404);
        } catch (\Exception $error) {
            return response()->json([
                "Error" => "Failed to delete role.",
                "Message" => $error->getMessage()
            ], 500);
        }
    }
}
