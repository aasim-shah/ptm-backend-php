<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
class HealthController extends Controller
{
    public function live() { return response()->json(['status'=>'ok'])->header('Cache-Control','no-store'); }
    public function ready()
    {
        try {
            DB::select('SELECT 1');
            if (app()->environment('production')) {
                foreach (['worker','scheduler'] as $component) {
                    if (!DB::table('workspace_runtime')->where('component',$component)->where('seen_at','>',now()->subMinutes(3))->exists()) { return response()->json(['status'=>'unavailable'],503); }
                }
            }
            return response()->json(['status'=>'ready'])->header('Cache-Control','no-store');
        } catch (\Throwable $e) { return response()->json(['status'=>'unavailable'],503)->header('Cache-Control','no-store'); }
    }
}
