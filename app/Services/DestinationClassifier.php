<?php

namespace App\Services;

class DestinationClassifier
{
    /**
     * @var array<string, array<int, array{name: string, keywords: array<int, string>}>>
     */
    private const RULES = [
        'apps' => [
            ['name' => 'Apple', 'keywords' => ['apple.com', 'itunes.apple', 'icloud', 'mzstatic', 'apple-dns']],
            ['name' => 'YouTube', 'keywords' => ['youtube', 'youtu.be', 'googlevideo', 'ytimg']],
            ['name' => 'TikTok', 'keywords' => ['tiktok', 'tiktokcdn', 'byteoversea', 'ibytedtos', 'pangle', 'pglstatp', 'musical.ly']],
            ['name' => 'Facebook', 'keywords' => ['facebook', 'fbcdn', 'fbsbx', 'messenger']],
            ['name' => 'Instagram', 'keywords' => ['instagram', 'cdninstagram']],
            ['name' => 'Netflix', 'keywords' => ['netflix', 'nflxvideo', 'nflximg', 'nflxso']],
            ['name' => 'Shopee', 'keywords' => ['shopee', 'shopeemobile']],
            ['name' => 'PalmPlay Store', 'keywords' => ['palmplaystore']],
            ['name' => 'Spotify', 'keywords' => ['spotify', 'scdn.co']],
            ['name' => 'WhatsApp', 'keywords' => ['whatsapp', 'whatsapp.net']],
        ],
        'sites' => [
            ['name' => 'MikroTik', 'keywords' => ['mikrotik']],
            ['name' => 'DNS Root Servers', 'keywords' => ['.root-servers.net']],
        ],
        'games' => [
            ['name' => 'Aha Game Center', 'keywords' => ['ahagamecenter']],
            ['name' => 'Call of Duty', 'keywords' => ['callofduty', 'codm', 'activision', 'demonware', 'garena', 'gfaren']],
            ['name' => 'Roblox', 'keywords' => ['roblox']],
            ['name' => 'Steam', 'keywords' => ['steam', 'steamcontent', 'steampowered']],
            ['name' => 'Epic Games', 'keywords' => ['epicgames', 'unrealengine']],
            ['name' => 'Riot Games', 'keywords' => ['riotgames', 'valorant', 'leagueoflegends']],
            ['name' => 'Mobile Legends', 'keywords' => ['mobilelegends', 'moonton']],
            ['name' => 'Minecraft', 'keywords' => ['minecraft']],
        ],
    ];

    /**
     * @return array{category: string, name: string}
     */
    public function classify(string $name, string $category = 'sites'): array
    {
        $normalized = strtolower(trim($name));
        $requestedCategory = in_array($category, ['apps', 'sites', 'games'], true) ? $category : 'sites';

        foreach (self::RULES as $matchedCategory => $rules) {
            foreach ($rules as $rule) {
                foreach ($rule['keywords'] as $keyword) {
                    if (str_contains($normalized, $keyword)) {
                        return [
                            'category' => $matchedCategory,
                            'name' => $rule['name'],
                        ];
                    }
                }
            }
        }

        return [
            'category' => $requestedCategory,
            'name' => $this->cleanHostname($name),
        ];
    }

    private function cleanHostname(string $name): string
    {
        $host = strtolower(trim($name));
        $host = preg_replace('/^https?:\/\//', '', $host) ?? $host;
        $host = preg_replace('/[\/?#].*$/', '', $host) ?? $host;
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        if ($host === '') {
            return 'Unknown';
        }

        return $host;
    }
}
