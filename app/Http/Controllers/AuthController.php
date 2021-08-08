<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuthResource;
use App\Jobs\GenerateAddressesJob;
use App\Jobs\SendResetPasswordEmailJob;
use App\Jobs\SendVerificationEmailJob;
use App\Models\User;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     ** path="/auth/register",
     *   tags={"Auth"},
     *   summary="Register",
     *   operationId="register",
     *
     *   @OA\Parameter(
     *      name="email",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="name",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="password",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *     @OA\Parameter(
     *      name="password_confirmation",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=400,
     *       description="Bad Request"
     *   ),
     *   @OA\Response(
     *      response=422,
     *      description="Unprocessed Entity"
     *   )
     *)
     **/
    public function register(): \Illuminate\Http\JsonResponse
    {
        // Set credentials and validate request
        $credentials = Arr::only(request()->all(), ['email', 'name', 'password', 'password_confirmation']);
        $validator = Validator::make($credentials, [
            'email' => ['required', 'unique:users,email', 'max:255'],
            'name' => ['required', 'unique:users,name', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->uncompromised()->numbers()]
        ]);
        if ($validator->fails()){
            return response()->json($validator->getMessageBag(), 422);
        }

        // Hash password and store user
        $otp = sha1(Arr::get($credentials, 'email').time());
        Arr::forget($credentials, 'password_confirmation');
        Arr::set($credentials, 'password', Hash::make($credentials['password']));
        Arr::set($credentials, 'verification_token', Hash::make($otp));
        Arr::set($credentials, 'verification_token_expiry', now()->addHour());
        $user = User::query()->create($credentials);
        $token = auth()->attempt(Arr::only(request()->all(), ['email', 'password']));

        // Dispatch relevant jobs
        SendVerificationEmailJob::dispatch($user, $otp);
        GenerateAddressesJob::dispatch($user);

        return $token ?
                    $this->respondWithTokenAndUser(User::query()->where('email', $credentials['email'])->first(), $token) :
                    response()->json(['error' => 'Something went wrong'], 400);
    }

    /**
     * @OA\Post(
     ** path="/auth/email/resend",
     *   tags={"Auth"},
     *   summary="Resend Email Verfication Link",
     *   operationId="resend email verfication link",
     *   security={{ "apiAuth": {} }},
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=400,
     *       description="Bad Request"
     *   )
     *)
     **/
    public function resendEmailVerificationLink(): \Illuminate\Http\JsonResponse
    {
        // Check if user has verified email
        $user = auth()->user();
        if (!is_null($user['email_verified_at'])) {
            return response()->json(["message" => "Email already verified."], 400);
        }

        // Update verification token
        $otp = sha1($user['email'].time());
        $user->update([
            'verification_token' => Hash::make($otp),
            'verification_token_expiry' => now()->addHour()
        ]);

        // Dispatch relevant jobs
        SendVerificationEmailJob::dispatch($user, $otp);

        return response()->json(["message" => "Email verification link sent to your email address"]);
    }

    /**
     * @OA\Post(
     ** path="/auth/email/verify",
     *   tags={"Auth"},
     *   summary="Verify email address",
     *   operationId="verify email address",
     *     @OA\Parameter(
     *      name="email",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *     @OA\Parameter(
     *      name="otp",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=422,
     *       description="Unprocessed Entity"
     *   ),
     *   @OA\Response(
     *      response=400,
     *       description="Bad Request"
     *   )
     *)
     **/
    public function verifyEmailAddress(): \Illuminate\Http\JsonResponse
    {
        // Set credentials and validate request
        $data = Arr::only(request()->all(), ['email', 'otp']);
        $validator = Validator::make($data, [
            'email' => ['required', 'email'],
            'otp' => ['required']
        ]);
        if ($validator->fails()){
            return response()->json($validator->getMessageBag(), 422);
        }

        // Check if user has verified email
        $user = User::query()->where('email', Arr::get($data, 'email'))->first();
        if (!is_null($user['email_verified_at'])) {
            return response()->json(["message" => "Email already verified."], 400);
        }

        // Verify otp
        if (!Hash::check(Arr::get($data, 'otp'), $user['verification_token'])) {
            return response()->json(["message" => "Invalid verification token."], 400);
        }

        // Verify otp has not expired
        if (now()->gt(Carbon::parse($user['verification_token_expiry']))){
            return response()->json(["message" => "Verification token expired."], 400);
        }

        // Set user email verified
        $user->update([
            'email_verified_at' => now()
        ]);

        return response()->json(["message" => "Email address verified successfully"]);
    }

    /**
     * @OA\Post(
     ** path="/auth/login",
     *   tags={"Auth"},
     *   summary="Login",
     *   operationId="login",
     *
     *   @OA\Parameter(
     *      name="email",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="password",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=422,
     *      description="Unprocessed Entity"
     *   )
     *)
     **/
    public function login(): \Illuminate\Http\JsonResponse
    {
        // Validate request
        $credentials = request(['email', 'password']);
        $validator = Validator::make($credentials, [
            'email' => 'required',
            'password' => 'required'
        ]);
        if ($validator->fails()){
            return response()->json($validator->messages(), 422);
        }

        // Generate token if credentials is valid
        if (! $token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Invalid login credentials'], 401);
        }

        return $this->respondWithTokenAndUser(User::all()->where('email',$credentials['email'])->first(), $token);
    }

    /**
     * @OA\Post(
     ** path="/auth/user",
     *   tags={"Auth"},
     *   summary="Get Authenticated User Information",
     *   operationId="get authenticated user information",
     *   security={{ "apiAuth": {} }},
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   )
     *)
     **/
    public function user(): \Illuminate\Http\JsonResponse
    {
        return response()->json(new AuthResource(auth()->user()));
    }

    /**
     * @OA\Post(
     ** path="/auth/logout",
     *   tags={"Auth"},
     *   summary="Logout",
     *   operationId="logout",
     *   security={{ "apiAuth": {} }},
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   )
     *)
     **/
    public function logout(): \Illuminate\Http\JsonResponse
    {
        auth()->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * @OA\Post(
     ** path="/auth/refresh",
     *   tags={"Auth"},
     *   summary="Refresh Authenticated User Token",
     *   operationId="refresh authenticated user token",
     *   security={{ "apiAuth": {} }},
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   )
     *)
     **/
    public function refresh(): \Illuminate\Http\JsonResponse
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * @OA\Post(
     ** path="/auth/password/reset/send-link",
     *   tags={"Auth"},
     *   summary="Send password reset link to email",
     *   operationId="send password reset link to email",
     *
     *   @OA\Parameter(
     *      name="email",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=422,
     *       description="Unprocessed Entity"
     *   )
     *)
     **/
    public function sendResetPasswordLink(): \Illuminate\Http\JsonResponse
    {
        // Validate request
        $validator = Validator::make(\request()->all(), [
            'email' => 'required',
        ]);
        if ($validator->fails()){
            return response()->json($validator->messages(), 422);
        }

        // Find user
        $user = User::query()->where('email', request('email'))->first();
        if (!$user) {
            return response()->json(['message' => 'We can\'t find a user with that email'], 404);
        }

        // create and store token
        $token = sha1($user['email'].time());
        DB::table('password_resets')->where('email', \request('email'))->delete();
        DB::table('password_resets')->insert([
            'email' => $user['email'],
            'token' => Hash::make($token),
            'created_at' => now()
        ]);

        // Dispatch relevant jobs
        SendResetPasswordEmailJob::dispatch($user, $token);

        return response()->json(['message' => 'Password reset link has been sent to your email']);
    }

    /**
     * @OA\Post(
     ** path="/auth/password/reset/change",
     *   tags={"Auth"},
     *   summary="Reset password",
     *   operationId="reset password",
     *
     *   @OA\Parameter(
     *      name="email",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="token",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="password",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *     @OA\Parameter(
     *      name="password_confirmation",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=400,
     *       description="Bad Request"
     *   ),
     *   @OA\Response(
     *      response=422,
     *      description="Unprocessed Entity"
     *   )
     *)
     **/
    public function resetPassword(): \Illuminate\Http\JsonResponse
    {
        // Set credentials and validate request
        $credentials = Arr::only(request()->all(), ['email', 'token', 'password', 'password_confirmation']);
        $validator = Validator::make($credentials, [
            'email' => ['required', 'email'],
            'token' => ['required', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->uncompromised()->numbers()]
        ]);
        if ($validator->fails()){
            return response()->json($validator->messages(), 422);
        }

        // Find user
        $user = User::query()->where('email', \request('email'))->first();
        if (!$user) {
            return response()->json(['message' => 'We can\'t find a user with that email'], 404);
        }

        // Find token
        $resetTokenField = DB::table('password_resets')->where('email', \request('email'))->first();
        if (!$resetTokenField)
            return response()->json(['message' => 'Reset token not found'], 404);

        // Verify token
        if (!Hash::check(request('token'), $resetTokenField->token))
            return response()->json(['message' => 'Reset token invalid'], 400);

        // Verify token expiry
        if (now()->gt(Carbon::parse($resetTokenField->created_at)->addHour()))
            return response()->json(['message' => 'Reset token expired'], 400);
        $user->update([
            'password' => Hash::make(\request('password'))
        ]);
        // Remove token from table
        DB::table('password_resets')->where('email', \request('email'))->delete();
        return response()->json(['message' => 'Password reset successfully']);
    }

        /**
     * @OA\Put(
     ** path="/auth/password/update",
     *   tags={"Auth"},
     *   summary="Update password",
     *   operationId="update password",
     *   security={{ "apiAuth": {} }},
     *
     *   @OA\Parameter(
     *      name="old_password",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="new_password",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *     @OA\Parameter(
     *      name="new_password_confirmation",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=400,
     *       description="Bad Request"
     *   ),
     *   @OA\Response(
     *      response=422,
     *      description="Unprocessed Entity"
     *   )
     *)
     **/
    public function updatePassword(): \Illuminate\Http\JsonResponse
    {
        // Set credentials and validate request
        $credentials = Arr::only(request()->all(), ['old_password', 'new_password', 'new_password_confirmation']);
        $validator = Validator::make($credentials, [
            'old_password' => ['required'],
            'new_password' => ['required', 'confirmed', Password::min(8)->mixedCase()->uncompromised()->numbers()]
        ]);
        if ($validator->fails()){
            return response()->json($validator->messages(), 422);
        }

        // Verify old password
        if (!Hash::check($credentials['old_password'], auth()->user()['password']))
            return response()->json(['message' => 'Old password is incorrect'], 400);

        auth()->user()->update([
            'password' => Hash::make($credentials['new_password'])
        ]);
        return response()->json(['message' => 'Password updated successfully']);
    }

    protected function respondWithTokenAndUser($user, $token): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'data' => new AuthResource($user),
            'access_token' => $token,
            'token_type' => 'bearer'
        ]);
    }

    protected function respondWithToken($token): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer'
        ]);
    }
}
