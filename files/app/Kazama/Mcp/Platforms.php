<?php

namespace Pterodactyl\Kazama\Mcp;

final class Platforms
{
    /** id => [label untuk prefix console, nama tampilan] */
    public const LIST = [
        'claude' => ['Claude', 'Claude (claude.ai / Desktop)'],
        'claude-code' => ['Claude-Code', 'Claude Code'],
        'opencode' => ['OpenCode', 'OpenCode'],
        'codex' => ['Codex', 'Codex'],
        'cursor' => ['Cursor', 'Cursor'],
        'chatgpt' => ['ChatGPT', 'ChatGPT'],
        'vscode' => ['VSCode', 'VS Code'],
        'gemini' => ['Gemini', 'Gemini CLI'],
        'antigravity' => ['Antigravity', 'Antigravity'],
        'other' => ['AI', 'Lainnya'],
    ];

    public static function valid(string $id): bool
    {
        return isset(self::LIST[$id]);
    }

    /** Label prefix console, contoh "Claude" → "[Claude-MCP]". */
    public static function label(string $platform, ?string $name = null): string
    {
        if ($platform === 'other' && $name) {
            $clean = preg_replace('/[^A-Za-z0-9]/', '', explode('(', $name)[0]);
            if ($clean !== '' && stripos($clean, 'kazama') !== 0) {
                return substr($clean, 0, 20);
            }
        }

        return (self::LIST[$platform] ?? self::LIST['other'])[0];
    }

    public static function guess(?string $clientName): string
    {
        $n = strtolower((string) $clientName);
        foreach ([
            'claude code' => 'claude-code', 'claude-code' => 'claude-code', 'claude' => 'claude', 'cursor' => 'cursor',
            'opencode' => 'opencode', 'codex' => 'codex', 'chatgpt' => 'chatgpt', 'openai' => 'chatgpt',
            'visual studio code' => 'vscode', 'vscode' => 'vscode', 'copilot' => 'vscode', 'gemini' => 'gemini', 'antigravity' => 'antigravity',
        ] as $needle => $id) {
            if ($n !== '' && str_contains($n, $needle)) {
                return $id;
            }
        }

        return 'other';
    }
}
