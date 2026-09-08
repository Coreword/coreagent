# WhatsApp Cloud API Setup

coreAgent's WhatsApp integration (`app/Http/Controllers/WhatsAppWebhookController.php`)
talks directly to Meta's WhatsApp Cloud API — no third-party BSP (Twilio,
360dialog, etc.) is required. This is a one-time setup you do yourself in
Meta's dashboard; nobody else can do it on your behalf because it needs your
own Facebook/Meta account and (eventually) your own business verification.

The code is already built and tested end-to-end (`tests/Feature/WhatsAppWebhookTest.php`).
It is inert — the webhook refuses every request — until the four env vars
below are filled in.

## 1. Create the Meta App

1. Go to https://developers.facebook.com/apps → **Create App**.
2. Choose **Other** → **Business** as the app type.
3. Give it a name (e.g. "CoreAgent WhatsApp").
4. In the app dashboard, find **WhatsApp** in the left sidebar under
   "Add products to your app" and click **Set up**.

## 2. Get a test phone number (free, no business verification needed yet)

Meta gives every new WhatsApp app one free test number automatically.

1. In **WhatsApp → API Setup**, you'll see a **From** number already
   provisioned (something like "Test Number: +1 555 xxx xxxx").
2. Under **To**, add up to 5 phone numbers (your own WhatsApp number, and
   anyone else's who needs to test) — each has to accept a verification code
   sent to their WhatsApp.
3. Note the **Phone number ID** shown on this page (a long numeric string,
   *not* the phone number itself) → this is `WHATSAPP_PHONE_NUMBER_ID`.

This test number is fully functional for development but expires/resets
periodically and only messages the 5 numbers you added. For a real customer-
facing number, see §5.

## 3. Get a permanent access token

The token shown by default on the API Setup page is temporary (expires in
24 hours) — fine for a first test, useless for production.

1. Go to **Business Settings** (business.facebook.com/settings) → **Users →
   System Users** → **Add** → create a system user with **Admin** role.
2. **Add Assets** → assign it your WhatsApp app.
3. **Generate New Token** → select the app → check the
   `whatsapp_business_messaging` and `whatsapp_business_management`
   permissions → **Generate Token**.
4. Copy it immediately (Meta only shows it once) → this is
   `WHATSAPP_ACCESS_TOKEN`.

## 4. Get the App Secret

**App dashboard → Settings → Basic → App Secret → Show.** This is
`WHATSAPP_APP_SECRET` — it's what `WhatsAppWebhookController::hasValidSignature()`
uses to verify every inbound webhook actually came from Meta.

## 5. Configure the webhook

This requires coreAgent to already be reachable over HTTPS at its real
domain (Meta will not call a `localhost` or self-signed URL).

1. **WhatsApp → Configuration → Webhook → Edit**.
2. **Callback URL**: `https://<your-coreagent-domain>/webhooks/whatsapp`
3. **Verify token**: any string you choose — put the *same* string in
   `WHATSAPP_VERIFY_TOKEN` in coreAgent's `.env` before clicking **Verify and
   Save** (Meta calls the callback URL immediately to confirm it echoes the
   token back — this is `WhatsAppWebhookController::verify()`).
4. **Manage** → subscribe to the **messages** field (this is what delivers
   inbound messages to the callback URL; other fields like `message_template_status_update`
   aren't needed).

## 6. Fill in coreAgent's `.env`

```
WHATSAPP_VERIFY_TOKEN=<the string you chose in step 5>
WHATSAPP_APP_SECRET=<from step 4>
WHATSAPP_ACCESS_TOKEN=<from step 3>
WHATSAPP_PHONE_NUMBER_ID=<from step 2>
```

Restart PHP-FPM / the queue worker after changing `.env` so the new values
are picked up. Send a WhatsApp message to the test number from one of the
verified "To" numbers — it should get a reply from the same agent that
answers the web chat, with the conversation visible nowhere in the web UI
(WhatsApp contacts get their own `channel = 'whatsapp'` conversation, kept
separate from portal users — see `App\Services\WhatsApp\WhatsAppConversationResolver`).

## 7. Going from test number to a real business number

When ready to use your own business phone number instead of Meta's free test
number:

1. **WhatsApp → API Setup → Add phone number**, verify ownership via SMS/call.
2. Meta requires **Business Verification** (company documents) before a
   non-test number can message people who haven't explicitly opted in via
   the test flow, and before you exceed the free tier's messaging limits.
3. Update `WHATSAPP_PHONE_NUMBER_ID` to the new number's id. Nothing else in
   the code changes.

## Voice notes

Text and voice notes are both handled inbound. A voice note is downloaded
from the Cloud API and transcribed with OpenAI's Whisper (see
`App\Services\WhatsApp\WhatsAppMediaTranscriber`), then run through the exact
same agent as typed text — the transcript is prefixed with 🎤 so it reads as
voice-sourced in the workspace view too.

This needs `OPENAI_API_KEY` set (config/providers.php's `openai.transcribe_model`,
default `whisper-1`) — no other configured provider (DeepSeek/Claude/Qwen)
does speech-to-text. Until that key is set, a voice note gets a reply saying
transcription isn't configured, rather than silently failing.

## What's NOT built yet

- **Images, documents, and location** still get a static "text or voice only
  for now" reply — see `WhatsAppWebhookController::handleInboundMessage()`.
  Routing images/documents into the existing Document Copilot pipeline is a
  reasonable next step but wasn't in scope here.
- **Replies are always text, never voice** — even when the inbound message
  was a voice note, the agent's answer comes back as a WhatsApp text message,
  not a synthesized voice note. Two-way voice (text-to-speech + uploading the
  result as a WhatsApp audio message) is a natural follow-up, not built here.
- **Only text replies** are sent outbound otherwise too (no images, buttons,
  or WhatsApp's interactive list/reply-button messages).
- **No opt-out/STOP handling.** Worth adding before sending to real customers
  at any volume — Meta can suspend a number for complaints if there's no way
  to unsubscribe.
