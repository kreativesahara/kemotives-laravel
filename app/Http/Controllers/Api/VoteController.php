<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VoteController extends Controller
{
    public function getUserVote(Request $request, $blogId)
    {
        $userId = auth('sanctum')->user()?->id;

        if (!$userId) {
            return response()->json(['vote' => 0]);
        }

        $userVote = Vote::where('user_id', $userId)
            ->where('blog_id', $blogId)
            ->first();

        if (!$userVote) {
            return response()->json(['vote' => 0]);
        }

        return response()->json(['vote' => $userVote->vote]);
    }

    public function handleVote(Request $request, $blogId)
    {
        $userId = auth('sanctum')->user()?->id;
        $voteValue = $request->input('vote');

        if (!$userId) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!in_array($voteValue, [1, -1, 0])) {
            return response()->json(['message' => 'Invalid vote value'], 400);
        }

        try {
            $existingVote = Vote::where('user_id', $userId)
                ->where('blog_id', $blogId)
                ->first();

            if ($existingVote) {
                if ($voteValue === 0) {
                    $existingVote->delete();
                } else {
                    $existingVote->update(['vote' => $voteValue]);
                }
            } elseif ($voteValue !== 0) {
                Vote::create([
                    'user_id' => $userId,
                    'blog_id' => $blogId,
                    'vote' => $voteValue
                ]);
            }

            $totalVotes = Vote::where('blog_id', $blogId)->sum('vote');

            return response()->json([
                'vote' => $voteValue,
                'totalVotes' => $totalVotes
            ]);
        } catch (\Exception $e) {
            Log::error('Error handling vote: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to process vote'], 500);
        }
    }

    public function getBlogVotes(Request $request, $blogId)
    {
        try {
            $userId = auth('sanctum')->user()?->id;

            $totalVotes = Vote::where('blog_id', $blogId)->sum('vote');

            $userVote = null;
            if ($userId) {
                $userVoteRecord = Vote::where('blog_id', $blogId)
                    ->where('user_id', $userId)
                    ->first();
                $userVote = $userVoteRecord ? $userVoteRecord->vote : null;
            }

            return response()->json([
                'totalVotes' => $totalVotes,
                'userVote' => $userVote,
            ]);
        } catch (\Exception $e) {
            Log::error("Error fetching votes: " . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch votes'], 500);
        }
    }

    public function getTotalVotes($blogId)
    {
        try {
            $totalVotes = Vote::where('blog_id', $blogId)->sum('vote');
            return response()->json(['totalVotes' => $totalVotes]);
        } catch (\Exception $e) {
            Log::error('Error getting total votes: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to get total votes'], 500);
        }
    }
}
