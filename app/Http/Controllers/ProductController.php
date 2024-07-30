<?php
namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::where("created_by", Auth::user()->id)
                   ->orderBy('created_at', 'desc')
                   ->get();
        return response()->json($products, 200);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Validate image
        ]);

        // Handle the image upload
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('images', 'public'); // Store image in 'public/images'
            $validatedData['image'] = str_replace('public/', '', $imagePath); // Remove 'public/' prefix
        }

        try {
            $product = Product::create([
                'name' => $validatedData['name'],
                'price' => $validatedData['price'],
                'description' => $validatedData['description'],
                'image' => $validatedData['image'] ?? null,
                'created_by' => Auth::id(),
                'created_at' => now()
            ]);
            return response()->json($product, 201);
        } catch (\Exception $e) {
            return response()->json(['Code' => 405, 'Status' => 'Fail', 'Message' => 'Failure In Adding Transactions: ' . $e->getMessage()], 500);
        }
    }


    public function show($id)
    {
        $product = Product::find($id);
        if (is_null($product)) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        return response()->json($product, 200);
    }

    public function update(Request $request, $id)
    {
        // Log raw request data
        Log::debug('Raw Request Data:', $request->all());

        // Check if the request is an instance of FormData
        if ($request->hasFile('image')) {
            Log::debug('Request contains file upload');
        }

        // Find the product
        $product = Product::find($id);
        if (is_null($product)) {
            Log::error("Product not found with ID: $id");
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Manually validate the request data and log validation errors
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Validate image
        ]);

        if ($validator->fails()) {
            Log::error('Validation Errors:', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();
        Log::debug('Validated Data:', $validatedData);

        // Handle the image upload if a new image is provided
        if ($request->hasFile('image')) {
            // Delete old image if it exists
            if ($product->image && Storage::exists('public/' . $product->image)) {
                Storage::delete('public/' . $product->image);
            }

            $imagePath = $request->file('image')->store('images', 'public');
            $validatedData['image'] = str_replace('public/', '', $imagePath); // Remove 'public/' prefix

        } else {
            // If no new image is provided, remove 'image' from the update data
            unset($validatedData['image']);
        }

        // Update the product with validated data
        $product->update($validatedData);

        return response()->json($product, 200);
    }


    public function destroy($id)
    {
        $product = Product::find($id);
        if (is_null($product)) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Delete the image if it exists
        if ($product->image && Storage::exists($product->image)) {
            Storage::delete($product->image);
        }

        $product->delete();
        return response()->json(null, 204);
    }
}
