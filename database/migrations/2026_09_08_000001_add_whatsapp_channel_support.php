<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // WhatsApp contacts are auto-provisioned as ordinary users (see
            // WhatsAppConversationResolver) so the existing conversations.user_id
            // foreign key and ConversationController ownership check need no
            // changes. E.164 format (e.g. "+85291234567"), as Meta sends it in
            // the webhook payload's "from" field.
            $table->string('phone_number', 32)->nullable()->unique()->after('email');
        });

        Schema::table('conversations', function (Blueprint $table) {
            // 'web' (default) or 'whatsapp'. A WhatsApp contact gets exactly one
            // ongoing conversation — there's no "+ New chat" button on a phone
            // number — so this plus user_id is how ConversationResolver finds it.
            $table->string('channel', 20)->default('web')->after('user_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            // Meta's message id, for de-duplicating webhook retries (Meta redelivers
            // on anything but a fast 200). Only ever set on inbound WhatsApp messages.
            $table->string('wa_message_id', 100)->nullable()->unique()->after('tool_call_id');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('wa_message_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('channel');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_number');
        });
    }
};
