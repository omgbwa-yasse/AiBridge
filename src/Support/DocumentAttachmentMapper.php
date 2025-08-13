<?php

namespace AiBridge\Support;

use AiBridge\Support\FileSecurity;

class DocumentAttachmentMapper
{
    private const IMAGE_PREFIX = 'image/';
    /**
     * Split attachments into generic files and inline text chunks suitable for providers lacking Files API.
     * Returns [ 'files' => [...], 'inlineTexts' => [...], 'image_files' => [...] ]
     */
    public static function toOllamaOptions(array $attachments): array
    {
        $files = [];
        $images = [];
        $inlineTexts = [];
        $fs = FileSecurity::fromConfig();
        foreach ($attachments as $att) {
            if ($att instanceof Document) {
                switch ($att->kind) {
                    case 'text':
                        if ($att->text !== null) { $inlineTexts[] = $att->text; }
                        break;
            case 'local':
                        if ($att->path && $fs->validateFile($att->path, false)) {
                            $mime = $att->mime ?: (mime_content_type($att->path) ?: 'application/octet-stream');
                            // image routed to image_files, others to files
                            $b64 = base64_encode(file_get_contents($att->path));
                if (str_starts_with($mime, self::IMAGE_PREFIX)) {
                                $images[] = $b64;
                            } else {
                                $files[] = [ 'name' => basename($att->path), 'type' => $mime, 'content' => $b64 ];
                            }
                        }
                        break;
                    case 'base64':
                        if ($att->base64 && $att->mime) {
                            if (str_starts_with($att->mime, self::IMAGE_PREFIX)) {
                                $images[] = $att->base64;
                            } else {
                                $files[] = [ 'name' => $att->title ?: 'document', 'type' => $att->mime, 'content' => $att->base64 ];
                            }
                        }
                        break;
                    case 'raw':
                        if ($att->raw && $att->mime) {
                            $b64 = base64_encode($att->raw);
                            if (str_starts_with($att->mime, self::IMAGE_PREFIX)) {
                                $images[] = $b64;
                            } else {
                                $files[] = [ 'name' => $att->title ?: 'document', 'type' => $att->mime, 'content' => $b64 ];
                            }
                        }
                        break;
                    case 'url':
                        // Ollama does not pull remote URLs; ignore for security and portability
                        break;
                    case 'chunks':
                        foreach ($att->chunks as $c) { $inlineTexts[] = (string)$c; }
                        break;
                    case 'file_id':
                        // Not applicable for Ollama
                        break;
                    default:
                        // ignore unknown kinds
                        break;
                }
            } elseif (is_string($att) && $fs->validateFile($att, false)) {
                $mime = mime_content_type($att) ?: 'application/octet-stream';
                $b64 = base64_encode(file_get_contents($att));
                if (str_starts_with($mime, self::IMAGE_PREFIX)) { $images[] = $b64; }
                else { $files[] = [ 'name' => basename($att), 'type' => $mime, 'content' => $b64 ]; }
            }
        }
        return [ 'files' => $files, 'image_files' => $images, 'inlineTexts' => $inlineTexts ];
    }

    /**
     * For OpenAI Responses path: build resources and tool hints.
     * - Inline texts merged into user message
     * - Files/URLs left to Files API flow (handled elsewhere)
     */
    public static function extractInlineTexts(array $attachments): array
    {
        $inline = [];
        foreach ($attachments as $att) {
            if ($att instanceof Document) {
                if ($att->kind === 'text' && $att->text !== null) { $inline[] = $att->text; }
                if ($att->kind === 'chunks') { foreach ($att->chunks as $c) { $inline[] = (string)$c; } }
            } elseif (is_string($att)) {
                // treat raw string as inline text
                $inline[] = $att;
            }
        }
        return $inline;
    }
}
