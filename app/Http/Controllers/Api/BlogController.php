<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Cloudinary\Cloudinary;

class BlogController extends Controller
{
    private function getCloudinary()
    {
        $cloudinaryUrl = env('CLOUDINARY_URL') ?? "cloudinary://" . env('CLOUDINARY_API_KEY') . ":" . env('CLOUDINARY_API_SECRET') . "@" . env('CLOUDINARY_CLOUD_NAME');
        return new Cloudinary($cloudinaryUrl);
    }

    private function extractPublicIdFromUrl($url)
    {
        if (preg_match('/\/v\d+\/(.+)\.\w+$/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function createBlog(Request $request)
    {
        $title = $request->input('title');
        $description = $request->input('description');
        $content = $request->input('content');
        $backlinks = $request->input('backlinks');
        $metaDescription = $request->input('metaDescription');
        $metaKeywords = $request->input('metaKeywords');
        $userId = $request->input('userId');
        $isPublished = filter_var($request->input('isPublished', false), FILTER_VALIDATE_BOOLEAN);

        if (!$title || !$description || !$content || !$metaDescription || !$metaKeywords || !$backlinks || !$userId) {
            return response()->json(['message' => 'All fields are required.'], 400);
        }

        $imageUrl = $request->input('imageUrl');

        try {
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $cloudinary = $this->getCloudinary();
                $result = $cloudinary->uploadApi()->upload($file->getRealPath(), [
                    'folder' => 'diksx/blogs',
                    'transformation' => [
                        ['width' => 1280, 'height' => 680, 'crop' => 'fill'],
                        ['quality' => 'auto']
                    ]
                ]);
                $imageUrl = $result['secure_url'];
            }
        } catch (\Exception $e) {
            Log::error("Cloudinary upload failed: " . $e->getMessage());
            return response()->json(['message' => 'Failed to upload image'], 500);
        }

        if (!$imageUrl) {
            return response()->json(['message' => 'Feature image is required.'], 400);
        }

        $slug = Str::slug($title);

        try {
            $blog = Blog::create([
                'title' => $title,
                'slug' => $slug,
                'description' => $description,
                'image_url' => $imageUrl,
                'content' => $content,
                'backlinks' => $backlinks,
                'author_id' => $userId,
                'meta_description' => $metaDescription,
                'meta_keywords' => $metaKeywords,
                'is_published' => $isPublished,
                'published_at' => $isPublished ? now() : null,
            ]);

            return response()->json($blog, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error creating blog post', 'error' => $e->getMessage()], 500);
        }
    }

    public function getAllBlogs()
    {
        $blogs = Blog::where('is_published', true)->orderBy('published_at')->get();
        return response()->json($blogs);
    }

    public function getBlogBySlug($slug)
    {
        $blog = Blog::where('slug', $slug)->first();
        if (!$blog) {
            return response()->json(['message' => 'Blog post not found'], 404);
        }
        return response()->json($blog);
    }

    public function updateBlog(Request $request, $id)
    {
        $blog = Blog::find($id);
        if (!$blog) {
            return response()->json(['message' => 'Blog post not found'], 404);
        }

        $updates = [];

        if ($request->has('title')) {
            $updates['title'] = $request->input('title');
            $updates['slug'] = Str::slug($updates['title']);
        }
        if ($request->has('description')) $updates['description'] = $request->input('description');
        if ($request->has('content')) $updates['content'] = $request->input('content');
        if ($request->has('backlinks')) $updates['backlinks'] = $request->input('backlinks');
        if ($request->has('metaDescription')) $updates['meta_description'] = $request->input('metaDescription');
        if ($request->has('metaKeywords')) $updates['meta_keywords'] = $request->input('metaKeywords');
        if ($request->has('isPublished')) {
            $isPublished = filter_var($request->input('isPublished'), FILTER_VALIDATE_BOOLEAN);
            $updates['is_published'] = $isPublished;
            if ($isPublished && !$blog->published_at) {
                $updates['published_at'] = now();
            }
        }

        $removeImage = filter_var($request->input('removeImage', false), FILTER_VALIDATE_BOOLEAN);
        $existingImage = $request->input('existingImage');

        try {
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $cloudinary = $this->getCloudinary();
                $result = $cloudinary->uploadApi()->upload($file->getRealPath(), [
                    'folder' => 'diksx/blogs',
                    'transformation' => [
                        ['width' => 1280, 'height' => 680, 'crop' => 'fill'],
                        ['quality' => 'auto']
                    ]
                ]);
                $updates['image_url'] = $result['secure_url'];
            } elseif ($removeImage) {
                $updates['image_url'] = '';
            } elseif ($existingImage) {
                $updates['image_url'] = $existingImage;
            }
        } catch (\Exception $e) {
            Log::error("Cloudinary update upload failed: " . $e->getMessage());
            return response()->json(['message' => 'Failed to upload image'], 500);
        }

        $blog->update($updates);
        return response()->json($blog);
    }

    public function deleteBlog($id)
    {
        $blog = Blog::find($id);
        if (!$blog) {
            return response()->json(['message' => 'Blog post not found'], 404);
        }

        if ($blog->image_url) {
            try {
                $publicId = $this->extractPublicIdFromUrl($blog->image_url);
                if ($publicId) {
                    $cloudinary = $this->getCloudinary();
                    $cloudinary->uploadApi()->destroy($publicId);
                }
            } catch (\Exception $e) {
                Log::error('Failed to delete Cloudinary image: ' . $e->getMessage());
            }
        }

        $blog->delete();
        return response()->json([], 204);
    }

    public function publishBlog($id)
    {
        $blog = Blog::find($id);
        if (!$blog) {
            return response()->json(['message' => 'Blog post not found'], 404);
        }

        $blog->update([
            'is_published' => true,
            'published_at' => now()
        ]);

        return response()->json($blog);
    }
}
