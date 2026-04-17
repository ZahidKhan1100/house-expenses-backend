<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'category' => ['required', 'string', 'in:bug_report,feature_idea,partnership,lead_magnet'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        Lead::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'category' => $validated['category'],
            'message' => $validated['message'],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Thanks — we received your message.'], 201);
    }
}
