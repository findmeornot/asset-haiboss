<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class AuthController extends Controller
{
    /**
     * Serialize user untuk response API: `avatar_url` di kolom DB cuma path
     * relatif ("avatars/xxx.webp"), jadi harus dikonversi ke URL publik
     * penuh sebelum dikirim ke frontend (kolom mentahnya tetap dipakai apa
     * adanya di Filament FileUpload & Storage::delete, jadi tidak diubah
     * di level model).
     */
    private function serializeUser(User $user): array
    {
        $data = $user->toArray();
        $data['avatar_url'] = $user->avatar_url
            ? Storage::disk('public')->url($user->avatar_url)
            : null;

        return $data;
    }

    /**
     * Login and issue a Sanctum API token.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Data tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $credentials = $validator->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        $deviceName = $credentials['device_name'] ?? $request->userAgent() ?? 'api';

        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'user' => $this->serializeUser($user->load('roles')),
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Logout: revoke current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    /**
     * Logout from all devices: revoke all tokens.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Berhasil logout dari semua perangkat.',
        ]);
    }

    /**
     * Get authenticated user's profile.
     */
    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->serializeUser($request->user()->load('roles')),
        ]);
    }

    /**
     * Update authenticated user's profile (name/email/avatar).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Data tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->fill($validator->validated());
        $user->save();

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => $this->serializeUser($user->load('roles')),
        ]);
    }

    /**
     * Change password for authenticated user.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Data tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini salah.'],
            ]);
        }

        $user->password = Hash::make($request->input('password'));
        $user->save();

        // Revoke other tokens, keep current session's token.
        $currentTokenId = $request->user()->currentAccessToken()?->id;
        $user->tokens()->when($currentTokenId, fn ($q) => $q->where('id', '!=', $currentTokenId))->delete();

        return response()->json([
            'message' => 'Password berhasil diubah.',
        ]);
    }

    /**
     * Upload/replace authenticated user's avatar.
     * Disimpan sebagai satu gambar persegi 256px (webp) di disk "public".
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Data tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $oldPath = $user->avatar_url;

        $manager = new ImageManager(Driver::class);
        $image = $manager->decode($request->file('avatar'))->cover(256, 256);

        $path = 'avatars/' . $user->id . '_' . Str::random(12) . '.webp';
        Storage::disk('public')->makeDirectory('avatars');
        $image->save(Storage::disk('public')->path($path));

        $user->avatar_url = $path;
        $user->save();

        // Hapus avatar lama setelah unggahan baru berhasil (PRD §FR-06.4)
        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json([
            'message' => 'Avatar berhasil diunggah.',
            'user' => $this->serializeUser($user->load('roles')),
            'avatar_url' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * Delete authenticated user's avatar.
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_url) {
            Storage::disk('public')->delete($user->avatar_url);
            $user->avatar_url = null;
            $user->save();
        }

        return response()->json([
            'message' => 'Avatar berhasil dihapus.',
            'user' => $this->serializeUser($user->load('roles')),
        ]);
    }
}
