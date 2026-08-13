<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Full {id,name,arguments}[] for an assistant turn that invoked tools.
            // tool_call_id/tool_name (already on this table) identify which single
            // call a role=tool result message is responding to.
            $table->json('tool_calls')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('tool_calls');
        });
    }
};
