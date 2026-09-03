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
            'message' => 'required|string',
            'history' => 'nullable|array',
        ]);

        $apiKey = env('GEMINI_API_KEY');
        $userMessage = $request->input('message');
        $history = $request->input('history', []);

        $systemInstruction = "Kamu adalah teman curhat virtual yang empatik, suportif, dan berbahasa santai serta ramah (gunakan bahasa Indonesia sehari-hari). Tugas utamamu HANYA mendengarkan keluh kesah, memvalidasi perasaan pengguna, dan memberi semangat. DILARANG KERAS menjawab pertanyaan akademis, teknis, koding, resep, atau pengetahuan umum. Jika pengguna bertanya di luar curahan hati/perasaan, tolak dengan halus dan ajak kembali membicarakan apa yang sedang mereka rasakan. Jika pengguna menyebutkan indikasi ingin menyakiti diri sendiri, beri validasi emosi dan sarankan mencari bantuan profesional/ahli.";

        $contents = [];

        foreach ($history as $chat) {
            $contents[] = [
                'role' => $chat['role'] === 'user' ? 'user' : 'model',
                'parts' => [['text' => $chat['text']]]
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $userMessage]]
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key={$apiKey}", [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $systemInstruction]
                    ]
                ],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 300,
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Maaf, aku lagi agak bingung. Bisa ceritakan lagi?';
                
                return response()->json([
                    'status' => 'success',
                    'reply' => $reply
                ]);
            }

            return response()->json([
                'status' => 'error',
                'status_code' => $response->status(),
                'google_error' => $response->json()
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}