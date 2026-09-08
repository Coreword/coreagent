<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class TemplatesController extends Controller
{
    /**
     * Unlike Scheduled/Integrations/Activity, this one is real: each template
     * is a prompt the agent can actually act on today with an existing tool
     * (list_cases/get_case, generate_video) or a pointer at a real setup doc
     * (WhatsApp). "Use this task" hands the text to Home via a query param.
     */
    public function index(): Response
    {
        return Inertia::render('Templates', [
            'templates' => [
                [
                    'tag' => 'Document Copilot',
                    'title' => 'Summarise an open case',
                    'description' => 'Ask the agent to look up a case by reference and summarise its status, documents, and any unresolved findings.',
                    'icon' => 'file',
                    'prompt' => '幫我搵返 case reference "REF-1234"，同我講下宜家嘅狀態、有幾多份文件，同埋有冇未解決嘅 finding。',
                ],
                [
                    'tag' => 'Document Copilot',
                    'title' => 'Case backlog check',
                    'description' => 'List every open case so you can see what still needs review before end of day.',
                    'icon' => 'list',
                    'prompt' => '幫我列出所有未完成嘅 case，包括 vertical 同宜家嘅狀態。',
                ],
                [
                    'tag' => 'Multimedia',
                    'title' => 'Generate a short video',
                    'description' => 'Describe a scene and have the agent submit it to the video-generation pipeline, then check on progress.',
                    'icon' => 'video',
                    'prompt' => '幫我生成一條短片：一個寧靜嘅海邊日出，鏡頭慢慢拉遠，帶少少電影感。',
                ],
                [
                    'tag' => 'Channels',
                    'title' => 'Set up a WhatsApp agent',
                    'description' => 'Walk through connecting a WhatsApp Business number so this same agent can answer customers over WhatsApp.',
                    'icon' => 'whatsapp',
                    'prompt' => '想幫個 WhatsApp agent 做設定，可以講下要做啲咩步驟嗎？',
                ],
            ],
        ]);
    }
}
