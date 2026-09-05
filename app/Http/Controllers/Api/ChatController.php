<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'nullable|string',
            'file' => 'nullable|array',
            'file.mime_type' => 'required_with:file|string',
            'file.data' => 'required_with:file|string',
            'history' => 'nullable|array',
        ]);

        $apiKey = env('GEMINI_API_KEY');
        $contents = [];

        foreach ($request->input('history', []) as $chat) {
            $text = trim($chat['text'] ?? '');
            if ($text !== '') {
                $contents[] = [
                    'role' => $chat['role'] === 'user' ? 'user' : 'model',
                    'parts' => [['text' => $text]]
                ];
            }
        }

        $userParts = [
            ['text' => trim($request->input('message') ?? '') ?: 'Tolong pahami apa yang ingin aku ceritakan dari lampiran ini ya...']
        ];

        if ($request->has('file') && !empty($request->input('file.data'))) {
            $data = $request->input('file.data');
            $mime = $request->input('file.mime_type');

            if (preg_match('/^data:([^;]+);base64,(.+)$/', $data, $m)) {
                $mime = $m[1];
                $data = $m[2];
            }

            $userParts[] = [
                'inline_data' => [
                    'mime_type' => $mime,
                    'data' => trim(str_replace(["\r", "\n", ' '], '', $data))
                ]
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => $userParts
        ];

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key={$apiKey}", [
                    'systemInstruction' => [
                        'parts' => [['text' => 'Kamu adalah teman curhat virtual yang empatik, suportif, dan berbahasa santai serta ramah (gunakan bahasa Indonesia sehari-hari). Tugas utamamu HANYA mendengarkan keluh kesah, memvalidasi perasaan pengguna, dan memberi semangat. Jika pengguna melampirkan gambar, foto, atau bukti pembayaran, pahami dan tanggapi konteks emosional serta isi gambar tersebut dengan penuh perhatian. DILARANG KERAS menjawab pertanyaan akademis, teknis, koding, resep, atau pengetahuan umum. Jika pengguna bertanya di luar curahan hati/perasaan, tolak dengan halus dan ajak kembali membicarakan apa yang sedang mereka rasakan. Jika pengguna menyebutkan indikasi ingin menyakiti diri sendiri, beri validasi emosi dan sarankan mencari bantuan profesional/ahli.']]
                    ],
                    'contents' => $contents,
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 1000,
                        'thinkingConfig' => [
                            'thinkingBudget' => 0,
                        ]
                    ]
                ]);

            if ($response->successful()) {
                return response()->json([
                    'status' => 'success',
                    'reply' => $response->json('candidates.0.content.parts.0.text') ?? 'Maaf, aku lagi agak bingung. Bisa ceritakan lagi?'
                ]);
            }

            return response()->json([
                'status' => 'error',
                'status_code' => $response->status(),
                'google_error' => $response->json()
            ], 500);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}