<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DatabaseRestoreUploadController extends Controller
{
    public function __invoke(Request $request)
    {
        $token = config('restore.token');

        abort_unless(
            is_string($token) &&
            $token !== '' &&
            hash_equals($token, (string) $request->header('X-DB-RESTORE-TOKEN')),
            403
        );

        $request->validate([
            'file' => ['required', 'file', 'max:1024'],
        ]);

        $path = $request->file('file')->storeAs(
            'restore',
            'production-data.sql',
            'local'
        );

        return response()->json([
            'uploaded' => true,
            'path' => $path,
        ]);
    }
}
