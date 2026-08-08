<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;

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

        } catch (\Exception $error) {
            return response()->json([
                "Error" => "Failed to fetch role.",
                "Message" => $error->getMessage()
            ], 500);
        }
    }

    // Update Role
    public function updateRole($id, Request $request)
    {
        try {
            $role = Role::findOrFail($id);           

            $request->validate([
                'name' => 'required|string',
                'slug' => 'nullable|string|max:1000'
           ]);

            $role->name = $request->name;
            $role->slug= $request->slug;
            $role->save();

            return response()->json($role);

        } catch (\Exception $error) {
            return response()->json([
                "Error" => "Failed to update role.",
                "Message" => $error->getMessage()
            ], 500);
        }
    }

    // Delete Role
    public function deleteRole($id)
    {       
        try {
            $role = Role::findOrFail($id);
            $role->delete();

            return response()->json("Role Deleted Successfully");

        } catch (\Exception $error) {
            return response()->json([
                "Error" => "Failed to delete role.",
                "Message" => $error->getMessage()
            ], 500);
        }
    }
}
