<?php

use App\Http\Controllers\Api\SettlementController;
use App\Http\Controllers\Api\InsightsController;
use App\Http\Controllers\Api\MateController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\TripMemberController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PushTokenController;
use App\Http\Controllers\Api\HouseWallController;
use App\Http\Controllers\Api\HouseWrappedController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\KarmaController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HouseController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\ReceiptScanController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\BuybackController;
use App\Http\Controllers\Api\HouseCalendarController;
use App\Http\Controllers\Api\RecordController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseAuditController;
use App\Http\Controllers\Api\CategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Password;


Route::prefix('v1')->group(function () {

    // Public auth (rate-limited — brute-force protection when APP_DEBUG=false behind HTTPS).
    Route::post('/signup', [AuthController::class, 'signup'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:25,1');
    // Route::get('/verify-email/{token}', [VerifyEmailController::class, 'verify']);
    Route::post('/resend-verification', [VerifyEmailController::class, 'resend']);
    Route::post('/check-email-verified', [AuthController::class, 'checkEmailVerified']);
    Route::get('/verify-email/{token}', [VerifyEmailController::class, 'verify'])
        ->name('verify.email');

    Route::post('/social-login', [SocialLoginController::class, 'login'])
        ->middleware('throttle:30,1');

    Route::post('/leads', [LeadController::class, 'store'])
        ->middleware('throttle:20,1');

    // Password reset routes

    Route::post('/forgot-password', function (Request $request) {

        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'email' => $user->email,
                'token' => hash('sha256', $token),
                'created_at' => now()
            ]
        );

        // 🔥 ALWAYS HTTPS web link
        $resetLink = "https://habimate.com/reset-password?token={$token}&email=" . urlencode($user->email);

        $html = view('emails.reset', compact('resetLink'))->render();

        sendMailgunEmail($user->email, "Reset Password", $html);

        return response()->json(['message' => 'Reset link sent']);
    });

    Route::post('/reset-password', function (Request $request) {

        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'password' => 'required|min:6|confirmed'
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Invalid request'], 400);
        }

        if (!hash_equals($record->token, hash('sha256', $request->token))) {
            return response()->json(['message' => 'Invalid token'], 400);
        }

        if (now()->diffInMinutes($record->created_at) > 60) {
            return response()->json(['message' => 'Token expired'], 400);
        }

        $user = User::where('email', $request->email)->first();

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password reset successful']);
    });

    // Protected routes (require Sanctum token)
    Route::middleware('auth:sanctum')->group(function () {

        // Pusher private channel auth (available at /api/v1/broadcasting/auth)
        Broadcast::routes();

        Route::post('/logout', [AuthController::class, 'logout']);

        // Expo push tokens
        Route::post('/push-tokens', [PushTokenController::class, 'store']);

        // House Wall
        Route::get('/house-wall', [HouseWallController::class, 'index']);
        Route::post('/house-wall/upload-signature', [HouseWallController::class, 'uploadSignature']);
        Route::post('/house-wall/snippets', [HouseWallController::class, 'createSnippet']);
        Route::post('/house-wall/snippets/discard-upload', [HouseWallController::class, 'discardSnippetCloudinaryUpload']);
        Route::post('/house-wall/polls', [HouseWallController::class, 'createPoll']);
        Route::post('/house-wall/polls/{post}/vote', [HouseWallController::class, 'vote']);
        Route::post('/house-wall/{post}/heart', [HouseWallController::class, 'toggleHeart']);
        Route::post('/house-wall/{post}/emoji', [HouseWallController::class, 'toggleEmojiReaction']);
        Route::delete('/house-wall/{post}', [HouseWallController::class, 'destroy']);
        Route::get('/house-wall/fridge-note', [HouseWallController::class, 'getFridgeNote']);
        Route::put('/house-wall/fridge-note', [HouseWallController::class, 'setFridgeNote']);
        Route::get('/house-wall/statuses', [HouseWallController::class, 'getStatuses']);
        Route::put('/house-wall/status', [HouseWallController::class, 'setStatus']);
        Route::get('/house-wall/running-low', [HouseWallController::class, 'runningLowList']);
        Route::post('/house-wall/running-low', [HouseWallController::class, 'runningLowPing']);

        // Houses
        Route::post('/houses/create', [HouseController::class, 'create']);
        Route::post('/houses/join', [HouseController::class, 'join']);
        Route::get('/houses/{house}', [HouseController::class, 'show']);
        Route::post('/leave-house', [HouseController::class, 'leaveHouse']);
        Route::post('/delete-account', [HouseController::class, 'deleteAccount']);

        Route::post('/join-house', [HouseController::class, 'joinHouse']);
        Route::post('/house/create', [HouseController::class, 'createHouse']);


        // Expenses
        Route::get('/expenses', [ExpenseController::class, 'index']);

        // Records
        Route::post('/records', [RecordController::class, 'store']);      // create
        Route::put('/records/{record}', [RecordController::class, 'update']); // update
        Route::delete('/records/{record}', [RecordController::class, 'destroy']);

        // Receipt quick-scan (Gemini OCR/extraction) — rate limited (see AppServiceProvider)
        Route::post('/receipts/extract', [ReceiptScanController::class, 'extract'])
            ->middleware('throttle:receipt-scan');

        Route::get('/profile', [UserController::class, 'profile']);
        Route::get('/users/search', [UserController::class, 'search']);


        Route::put('/houses/{id}', [HouseController::class, 'update']);
        Route::put('/house/join/qr', [HouseController::class, 'joinByQRCode']);
        Route::get('/house/current', [HouseController::class, 'current']);


        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Expense audit trail (house-scoped)
        Route::get('/expense-audit', [ExpenseAuditController::class, 'index']);

        // Gamification
        Route::get('/leaderboard', [LeaderboardController::class, 'index']);
        Route::get('/house-wrapped', [HouseWrappedController::class, 'index']);
        Route::post('/karma/log', [KarmaController::class, 'log']);

        Route::get('/mates', [MateController::class, 'index']);
        Route::post('/mates/{id}/approve', [MateController::class, 'approve']);
        Route::post('/mates/{id}/reject', [MateController::class, 'reject']);
        Route::put('/mates/{id}', [MateController::class, 'update']);
        Route::delete('/mates/{id}', [MateController::class, 'destroy']);

        // Vacation / guest calendar (Who's Home)
        Route::get('/house/calendar', [HouseCalendarController::class, 'index']);
        Route::get('/house/calendar/presence', [HouseCalendarController::class, 'presence']);
        Route::post('/house/calendar', [HouseCalendarController::class, 'store']);
        Route::delete('/house/calendar/{id}', [HouseCalendarController::class, 'destroy']);

        Route::get('/payments/{month?}', [PaymentController::class, 'index']);
        Route::get('/house/{houseId}/insights', [InsightsController::class, 'index']);
        Route::get('/house/current/expenses', [InsightsController::class, 'getExpensesByMonth']);

        // Settlement
        // Route::post('/settlements', [SettlementController::class, 'store']);

        Route::get('/settlements', [SettlementController::class, 'index']);
        Route::post('/settlements/generate', [SettlementController::class, 'generate']);
        Route::post('/settlements/{id}/mark-paid', [SettlementController::class, 'markPaid']);
        Route::post('/buybacks', [BuybackController::class, 'store']);

        // Trips

        // Trips
        Route::get('/trips', [TripController::class, 'index']);
        Route::post('/trips', [TripController::class, 'store']);
        Route::get('/trips/{tripId}', [TripController::class, 'show']);
        Route::put('/trips/{tripId}', [TripController::class, 'update']);
        Route::delete('/trips/{tripId}', [TripController::class, 'destroy']);

        // Trip Members
        Route::get('/trips/{tripId}/members', [TripMemberController::class, 'index']);
        Route::post('/trips/{tripId}/members', [TripMemberController::class, 'store']);
        Route::delete('/trips/{tripId}/members/{userId}', [TripMemberController::class, 'destroy']);

    });
});


Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
});





