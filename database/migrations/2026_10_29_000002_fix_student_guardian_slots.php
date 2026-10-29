<?php

use App\Models\Students;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Children added from the app were put in mother_id or father_id from the parent's gender,
 * which registration never saved, so most landed in mother_id. Moves each single-parent
 * child to the slot of the relationship the parent picked (e.g. "Father" -> father_id).
 * Only rows with exactly one filled slot are touched, so two-parent records stay as they are.
 */
return new class extends Migration
{
    public function up()
    {
        $students = DB::table('students')
            ->whereNotNull('relationship')
            ->whereRaw('(father_id IS NOT NULL) + (mother_id IS NOT NULL) + (guardian_id IS NOT NULL) = 1')
            ->get(['id', 'relationship', 'father_id', 'mother_id', 'guardian_id']);

        foreach ($students as $student) {
            $slot = Students::guardianSlot($student->relationship, 'male');
            if ($slot !== Students::guardianSlot($student->relationship, 'female')) {
                continue; // unknown relationship: the slot would only be a gender guess
            }
            $parentId = $student->father_id ?? $student->mother_id ?? $student->guardian_id;
            if ((int) $student->{$slot} === (int) $parentId) {
                continue;
            }
            DB::table('students')->where('id', $student->id)->update([
                'father_id' => null,
                'mother_id' => null,
                'guardian_id' => null,
                $slot => $parentId,
            ]);
        }
    }

    public function down()
    {
        // Data fix only.
    }
};
