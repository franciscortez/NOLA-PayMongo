<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tokens = DB::table('location_tokens')->get();

        foreach ($tokens as $token) {
            $isEncrypted = false;
            try {
                Crypt::decryptString($token->access_token);
                $isEncrypted = true;
            } catch (\Exception $e) {
                // Not encrypted yet.
            }

            if (!$isEncrypted) {
                DB::table('location_tokens')
                    ->where('id', $token->id)
                    ->update([
                        'access_token' => Crypt::encryptString($token->access_token),
                        'refresh_token' => Crypt::encryptString($token->refresh_token),
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tokens = DB::table('location_tokens')->get();

        foreach ($tokens as $token) {
            try {
                $decryptedAccess = Crypt::decryptString($token->access_token);
                $decryptedRefresh = Crypt::decryptString($token->refresh_token);

                DB::table('location_tokens')
                    ->where('id', $token->id)
                    ->update([
                        'access_token' => $decryptedAccess,
                        'refresh_token' => $decryptedRefresh,
                    ]);
            } catch (\Exception $e) {
                // Already plaintext
            }
        }
    }
};
