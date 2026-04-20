<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Http\Request;

class AttributeController extends Controller
{
    // GET /attributes - gives list of all attributes with their values

    public function index()
    {
        return response()->json(
            Attribute::with('values')->get()
        );
    }

    // POST /attributes - [creates a new attribute(llike material, color) with values]

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:attributes,name']);
        $attribute = Attribute::create(['name' => $request->name]);

        return response()->json($attribute, 201);
    }

    // POST /attributes/{attribute}/values -  [adds a new value to an attribute(like cotton to material)]

    public function addValue(Request $request, Attribute $attribute)
    {
        $request->validate(['value' => 'required|string']);
        $value = $attribute->values()->create(['value' => $request->value]);

        return response()->json($value, 201);
    }

    // DELETE /attribute-values/{value} - [removes an attribute value]

    public function deleteValue(AttributeValue $value)
    {
        $value->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    /*DELETE attributes
    public function deleteAttribute(Attribute $attribute)
    {
        $attribute->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
    */
}
