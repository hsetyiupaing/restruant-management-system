<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

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

        $tables = [
            'restaurants', 'users', 'restaurant_members', 'categories',
            'dietary_tags', 'menu_items', 'dietary_tag_menu_item',
            'dining_tables', 'reservations', 'reservation_status_history',
            'orders', 'order_items', 'order_status_history', 'payments',
            'delivery_details', 'customer_food_restrictions',
        ];

        $sql = $request->file('file')->get();
        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
        $inserts = [];
        $foundTables = [];

        foreach ($statements as $statement) {
            $statement = trim($statement);

            $statement = preg_replace(
                '/\A(?:--[^\r\n]*(?:\r?\n|$)|#[^\r\n]*(?:\r?\n|$))+/',
                '',
                $statement
            );

            if (preg_match('/^INSERT INTO `([a-zA-Z0-9_]+)`/i', $statement, $match)) {
                $table = $match[1];

                abort_unless(in_array($table, $tables, true), 422, 'Unexpected table in SQL file.');

                $inserts[] = $statement;
                $foundTables[$table] = true;
            }
        }

        abort_unless(count($inserts) > 0, 422, 'No INSERT statements found.');
        abort_unless(count($foundTables) === count($tables), 422, 'SQL file is missing expected tables.');

        $connection = DB::connection();

        foreach ($tables as $table) {
            abort_if(
                $connection->table($table)->exists(),
                409,
                "Table {$table} is not empty. Import cancelled."
            );
        }

        $connection->beginTransaction();

        try {
            $connection->statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($inserts as $statement) {
                $connection->unprepared($statement);
            }

            $connection->statement('SET FOREIGN_KEY_CHECKS=1');
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            $connection->statement('SET FOREIGN_KEY_CHECKS=1');

            throw $exception;
        }

        $counts = [];

        foreach ($tables as $table) {
            $counts[$table] = $connection->table($table)->count();
        }

        return response()->json([
            'success' => true,
            'imported_tables' => count($counts),
            'counts' => $counts,
        ]);
    }
}
