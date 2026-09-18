<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-db', function() {
    try {
        DB::connection()->getPdo();
        return response()->json([
            'status' => 'success',
            'message' => 'Succesfully connected to the database',
        ], 200);
    } catch (\Exception $e){
        return response()->json([
            'status' => 'error',
            'error' => $e->getMessage()
        ], 500);
    }
});

?>