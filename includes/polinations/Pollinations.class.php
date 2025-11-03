<?php

class Pollinations
{
    // Construit une URL d'image Pollinations en fonction du prompt
    public static function imageUrl(string $name, string $typeName = '', ?int $heads = null, ?int $seed = null): string
    {
        $tpl = self::readTemplate(__DIR__ . '/monster.image.prompt')
            ?: 'Mythical creature: {name}, type: {type}, heads: {heads}. Highly detailed, fantasy art, trending on artstation.';

        $prompt = strtr($tpl, [
            '{name}' => trim($name),
            '{type}' => trim($typeName),
            '{heads}' => ($heads !== null ? (string)$heads : 'unknown'),
        ]);

        $q = rawurlencode($prompt);
        // Ajoute un seed pour rendre l'image unique par créature
        $seedParam = $seed !== null ? ('&seed=' . urlencode((string)$seed)) : '';
        return "https://image.pollinations.ai/prompt/{$q}?width=768&height=768{$seedParam}";
    }

    // Génère une petite description via l'API texte de Pollinations (fallback si indisponible)
    public static function generateDescription(string $name, string $typeName = ''): string
    {
        $tpl = self::readTemplate(__DIR__ . '/monster.description.prompt')
            ?: 'Write a short, vivid description (2-3 sentences) of a mythical creature named {name}, of type {type}.';

        $prompt = strtr($tpl, [ '{name}' => trim($name), '{type}' => trim($typeName) ]);

        $url = 'https://text.pollinations.ai/' . rawurlencode($prompt);
        $ctx = stream_context_create(['http' => ['timeout' => 6]]);
        $txt = @file_get_contents($url, false, $ctx);
        if ($txt && is_string($txt)) {
            $txt = trim($txt);
            // Pollinations peut renvoyer du texte brut; on tronque gentiment
            if ($txt !== '') {
                return mb_substr($txt, 0, 800);
            }
        }
        // Fallback local si l'appel échoue
        return sprintf("%s est une créature de type %s, née des légendes. Sa présence impose le respect et inspire la crainte.", $name, ($typeName ?: 'inconnu'));
    }

    // Scores simples à partir du nom/type , random
    public static function scores(string $name, string $typeName = ''): array
    {
        $seed = crc32(strtolower($name . '|' . $typeName));
        $rng = self::lcg($seed);
        $health = 60 + (int)round($rng() * 40);   // 60..100
        $def = 30 + (int)round($rng() * 50);      // 30..80
        $atk = 30 + (int)round($rng() * 70);      // 30..100
        return [$health, $def, $atk];
    }

    // Propose un nom court à partir du prompt
    public static function nameFromPrompt(string $prompt): string
    {
        $prompt = trim($prompt);
        if ($prompt === '') return 'Créature Anonyme';

        // Essaye une requête à l'API texte (optionnel)
        $ask = 'Donne un nom court (1 à 3 mots) pour une créature mythologique basée sur: ' . $prompt . '. Réponds uniquement par le nom.';
        $url = 'https://text.pollinations.ai/' . rawurlencode($ask);
        $ctx = stream_context_create(['http' => ['timeout' => 6]]);
        $txt = @file_get_contents($url, false, $ctx);
        if ($txt && is_string($txt)) {
            $name = trim($txt);
            // Nettoyage simple
            $name = preg_replace('/[\r\n]+/', ' ', $name);
            $name = trim($name, " \t-—:;.,!?");
            if ($name !== '' && mb_strlen($name) <= 60) {
                return self::ucwordsUnicode($name);
            }
        }
        // Fallback local: prend 2-3 mots clefs du prompt
        $words = preg_split('/[^\p{L}\p{N}\-]+/u', $prompt, -1, PREG_SPLIT_NO_EMPTY);
        $words = array_slice($words, 0, 3);
        $name = implode(' ', array_map([self::class, 'ucwordsUnicode'], $words));
        return $name !== '' ? $name : 'Créature Anonyme';
    }

    private static function readTemplate(string $file): ?string
    {
        if (is_file($file)) {
            $txt = trim((string)@file_get_contents($file));
            return $txt !== '' ? $txt : null;
        }
        return null;
    }

    // Petit générateur pseudo-aléatoire 
    private static function lcg(int $seed): callable
    {
        $mod = 2**31 - 1; $a = 1103515245; $c = 12345; $state = $seed & 0x7fffffff;
        return function() use (&$state, $a, $c, $mod) { $state = (int)(($a * $state + $c) % $mod); return $state / $mod; };
    }

    private static function ucwordsUnicode(string $s): string
    {
        return preg_replace_callback('/\b(\p{L})(\p{L}*)/u', function($m){
            return mb_strtoupper($m[1]) . mb_strtolower($m[2]);
        }, $s);
    }
}
