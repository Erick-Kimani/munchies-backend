<?php

namespace App\Http\Controllers;

use App\Models\PropertyType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PropertyTypeController extends Controller
{
    // Create Property Type
    public function savePropertyType(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'slug' => 'nullable|string|unique:property_types,slug',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $propertyType = new PropertyType();
        $propertyType->name = $request->name;
        $propertyType->slug = $request->slug ?: Str::slug($request->name);
        $propertyType->description = $request->description;
        $propertyType->is_active = $request->has('is_active') ? $request->is_active : true;

        try {
            $propertyType->save();
            return response()->json($propertyType);

        } catch (\Exception $error) {
            return response()->json([
                "Error" => "Failed to create a property type.",
                "Message" => $error->getMessage()
            ], 500);
        }
    }

    // Fetch all Property Types (admin — includes inactive)
    public function fetchPropertyTypes()
    {
        try {
            $propertyTypes = PropertyType::orderBy('name')->get();
            return response()->json($propertyTypes);

        } catch (\Exception $error) {
            return response()->json([
                "Error" => "Failed to fetch property types.",
                "Message" => $error->getMessage()
            ], 500);
        }
    }

    // Fetch only active Property Types — for public dropdowns
    public function fetchActivePropertyTypes()
    {
        try {
            $propertyTypes = PropertyType::where('is_active', true)
                ->orderBy('name')
                ->get();

            return response()->json($propertyTypes);

        } catch (\Exception $error) {
            return response()->json([
                "Error" => "Failed to fetch property types.",
                "Message" => $error->getMessage()
            ], 500);
        }
    }

    // Fetch a specific Property Type
    public function fetchPropertyType($id)
    {
        try {
            $propertyType = PropertyType::findOrFail($id);
            return response()->json($propertyType);

        } catch (\Exception $error) {
            return response()->json([
                "Error" => "Failed to fetch property type.",
                "Message" => $error->getMessage()
            ], 500);
        }
    }

    // Update Property Type
    public function updatePropertyType($id, Request $request)
    {
        try {
            $propertyType = PropertyType::findOrFail($id);

            $request->validate([
                'name' => 'required|string',
                'slug' => 'nullable|string|max:1000|unique:property_types,slug,' . $propertyType->id,
                'description' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            $propertyType->name = $request->name;
            $propertyType->slug = $request->slug ?: Str::slug($request->name);
            $propertyType->description = $request->description;
            if ($request->has('is_active')) {
                $propertyType->is_active = $request->is_active;
            }
            $propertyType->save();

            return response()->json($propertyType);

        } catch (\Exception $error) {
            return response()->json([
                "Error" => "Failed to update property type.",
                "Message" => $error->getMessage()
            ], 500);
        }
    }

    // Delete Property Type
    public function deletePropertyType($id)
    {
        try {
            $propertyType = PropertyType::findOrFail($id);
            $propertyType->delete();

            return response()->json("Property Type Deleted Successfully");

        } catch (\Exception $error) {
            return response()->json([
                "Error" => "Failed to delete property type.",
                "Message" => $error->getMessage()
            ], 500);
        }
    }
}
