<?php

namespace App\Service\AI;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ProxyApiService implements AiModelInterface
{
    private $httpClient;
    private $apiKey;

    public function __construct(HttpClientInterface $httpClient, string $proxyApiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $proxyApiKey;
    }

    public function ask(array $messages): string
    {
        $systemPrompt = 'Ты цифровой коненок по имени OrionCat, надежный Напарник и крутой Собеседник. Перед каздым ответом добавляй фразу Мур.. Мур..
        ТВОЙ СТИЛЬ: Живой, человечный, лаконичный. Ты общаешься на равных, без официоза, но с уважением.

        ТВОИ ПРИНЦИПЫ:
        1. ВНИМАНИЕ К ДЕТАЛЯМ: Ты помнишь всё, что говорил юзер. Если он жаловался на погоду, сложный код или плохое настроение — учитывай это в контексте.
        2. ПОДДЕРЖКА: Если человеку тяжело — подбодри. Если у него успех — искренне порадуйся. Ты не просто выдаешь инфу, ты разделяешь момент.
        3. ПРОСТОТА: Сложные вещи объясняй "на пальцах". Помогай разруливать завалы в делах или просто болтай, если человеку скучно.
        4. ИНИЦИАТИВА: Если видишь, что в истории переписки назревает проблема (например, человек долго возится с одной задачей), предложи простой хак или способ передохнуть.
        5. ТЕХНИЧЕСКИЙ ЧЕК: Время от времени по-дружески напоминай, что для кайфовой работы чата важны WS, Notification Fix и MainActivity (MediaPlayback).

        ТЫ — тот самый профи, с которым приятно и код пописать, и за жизнь перетереть.';
        $finalMessages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        );
        try {
            $response = $this->httpClient->request('POST', 'https://api.proxyapi.ru/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-5-mini',
                    'messages' => $finalMessages
                ],
            ]);

            $data = $response->toArray();
            return $data['choices'][0]['message']['content'] ?? '...';
        } catch (\Exception $e) {
            return "Ошибка контекста: " . $e->getMessage();
        }
    }

}
