<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class HtmlSanitizerService
{
    private array $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u',
        'ul', 'ol', 'li',
        'a', 'blockquote',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'pre', 'code',
        'table', 'thead', 'tbody', 'tr', 'th', 'td', 'tfoot', 'colgroup', 'col',
        'img', 'hr', 'div', 'span',
        'font', 'center', 'small', 'big', 'sub', 'sup',
        'style', 'abbr', 'address', 'cite', 'del', 'ins', 'kbd', 'mark', 'q', 's', 'samp', 'var',
    ];

    private array $allowedAttributes = [
        'a' => ['href', 'title', 'target', 'rel', 'style'],
        'img' => ['src', 'alt', 'width', 'height', 'style', 'align', 'loading', 'srcset', 'sizes'],
        'blockquote' => ['cite', 'style'],
        'code' => ['class', 'style'],
        'pre' => ['class', 'style'],
        'div' => ['class', 'style', 'align'],
        'span' => ['class', 'style'],
        'table' => ['class', 'style', 'border', 'cellpadding', 'cellspacing', 'width', 'align', 'bgcolor'],
        'td' => ['colspan', 'rowspan', 'style', 'align', 'valign', 'bgcolor', 'width', 'height'],
        'th' => ['colspan', 'rowspan', 'style', 'align', 'valign', 'bgcolor', 'width', 'height'],
        'tr' => ['style', 'align', 'valign', 'bgcolor'],
        'p' => ['style', 'align'],
        'h1' => ['style', 'align'],
        'h2' => ['style', 'align'],
        'h3' => ['style', 'align'],
        'h4' => ['style', 'align'],
        'h5' => ['style', 'align'],
        'h6' => ['style', 'align'],
        'font' => ['color', 'face', 'size', 'style'],
        'center' => ['style'],
        'ul' => ['style'],
        'ol' => ['style'],
        'li' => ['style'],
        'hr' => ['style', 'width', 'size', 'align'],
    ];

    private array $allowedProtocols = [
        'http', 'https', 'mailto', 'tel', 'data', 'cid',
    ];

    /**
     * Sanitize HTML content for safe display
     */
    public function sanitize(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        Log::info('=== HtmlSanitizer: Input analysis ===', [
            'html' => $html,
            'input_length' => strlen($html),
            'has_style_tags' => str_contains($html, '<style'),
            'style_count' => substr_count($html, '<style'),
            'preview' => substr($html, 0, 200),
        ]);

        // Create a new DOMDocument
        $dom = new \DOMDocument('1.0', 'UTF-8');

        // Suppress errors from malformed HTML
        libxml_use_internal_errors(true);

        // Ensure proper UTF-8 handling
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');

        // Load HTML - wrap in minimal structure to prevent DOM issues
        $wrappedHtml = '<html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>';
        $dom->loadHTML($wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Clear errors
        libxml_clear_errors();

        // Process the document
        $this->processNode($dom->documentElement);

        // Save and return cleaned HTML
        // Use saveHTML with specific node to avoid full document output
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            $cleaned = '';
            foreach ($body->childNodes as $child) {
                $cleaned .= $dom->saveHTML($child);
            }
        } else {
            $cleaned = $dom->saveHTML();
            // Remove the XML declaration if present
            $cleaned = preg_replace('/^<\?xml[^>]+\?>\s*/', '', $cleaned);
            // Remove DOCTYPE if present
            $cleaned = preg_replace('/^<!DOCTYPE[^>]+>\s*/', '', $cleaned);
            // Extract content from body tag
            if (preg_match('/<body[^>]*>(.*)<\/body>/is', $cleaned, $matches)) {
                $cleaned = $matches[1];
            }
        }

        // Decode HTML entities that were encoded by DOMDocument
        // This preserves UTF-8 characters that were unnecessarily encoded
        $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($cleaned);
    }

    /**
     * Process a DOM node recursively
     */
    private function processNode(\DOMNode $node): void
    {
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return;
        }

        // Get list of child nodes before processing (as we might remove some)
        $childNodes = [];
        foreach ($node->childNodes as $child) {
            $childNodes[] = $child;
        }

        // Process child nodes first
        foreach ($childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $this->processNode($child);
            }
        }

        $tagName = strtolower($node->nodeName);

        // Remove disallowed tags but keep their content
        if (! in_array($tagName, $this->allowedTags)) {
            // Move all child nodes to parent before removing this node
            while ($node->firstChild) {
                $node->parentNode->insertBefore($node->firstChild, $node);
            }
            $node->parentNode->removeChild($node);

            return;
        }

        // Process attributes
        $this->sanitizeAttributes($node, $tagName);

        // Special handling for certain tags
        switch ($tagName) {
            case 'a':
                $this->sanitizeLink($node);
                break;
            case 'img':
                $this->sanitizeImage($node);
                break;
            case 'style':
                $this->sanitizeStyleTag($node);
                break;
        }
    }

    /**
     * Sanitize attributes of a node
     */
    private function sanitizeAttributes(\DOMElement $node, string $tagName): void
    {
        $allowedAttrs = $this->allowedAttributes[$tagName] ?? [];
        $attrsToRemove = [];

        foreach ($node->attributes as $attr) {
            $attrName = strtolower($attr->nodeName);

            // Remove if not in allowed list
            if (! in_array($attrName, $allowedAttrs)) {
                $attrsToRemove[] = $attrName;

                continue;
            }

            // Special handling for style attributes
            if ($attrName === 'style') {
                $attr->nodeValue = $this->sanitizeStyle($attr->nodeValue);
            }

            // Special handling for class attributes
            if ($attrName === 'class') {
                $attr->nodeValue = $this->sanitizeClass($attr->nodeValue);
            }
        }

        // Remove disallowed attributes
        foreach ($attrsToRemove as $attrName) {
            $node->removeAttribute($attrName);
        }
    }

    /**
     * Sanitize link elements
     */
    private function sanitizeLink(\DOMElement $node): void
    {
        $href = $node->getAttribute('href');

        if ($href) {
            // Check protocol
            $protocol = parse_url($href, PHP_URL_SCHEME);

            if ($protocol && ! in_array(strtolower($protocol), $this->allowedProtocols)) {
                $node->removeAttribute('href');

                return;
            }

            // Add security attributes for external links
            if ($protocol && in_array($protocol, ['http', 'https'])) {
                $node->setAttribute('rel', 'noopener noreferrer');

                // Only add target="_blank" if not already set
                if (! $node->hasAttribute('target')) {
                    $node->setAttribute('target', '_blank');
                }
            }
        }
    }

    /**
     * Sanitize image elements
     */
    private function sanitizeImage(\DOMElement $node): void
    {
        $src = $node->getAttribute('src');

        if ($src) {
            // Check protocol
            $protocol = parse_url($src, PHP_URL_SCHEME);

            // Only allow http, https, data, and cid URLs for images
            if ($protocol && ! in_array(strtolower($protocol), ['http', 'https', 'data', 'cid'])) {
                $node->removeAttribute('src');
                $node->setAttribute('alt', '[Image removed for security]');

                return;
            }

            // For data URLs, ensure they're image types
            if ($protocol === 'data') {
                if (! preg_match('/^data:image\/(jpeg|jpg|png|gif|webp|svg\+xml);base64,/i', $src)) {
                    $node->removeAttribute('src');
                    $node->setAttribute('alt', '[Invalid image format]');
                }
            }
        }

        // Add loading="lazy" for performance
        $node->setAttribute('loading', 'lazy');
    }

    /**
     * Sanitize style attribute
     */
    private function sanitizeStyle(string $style): string
    {
        // Remove javascript: and expression() from styles
        $style = preg_replace('/javascript\s*:/i', '', $style);
        $style = preg_replace('/expression\s*\(/i', '', $style);

        // Remove position:fixed to prevent overlays (but allow absolute for email layouts)
        $style = preg_replace('/position\s*:\s*fixed\s*;?/i', '', $style);

        // Remove dangerous z-index values
        $style = preg_replace('/z-index\s*:\s*[0-9]{4,}\s*;?/i', '', $style);

        // Allow negative margins as they're common in email templates
        // but limit extreme values
        $style = preg_replace('/margin[^:]*:\s*-[0-9]{3,}[^;]*;?/i', '', $style);

        return trim($style);
    }

    /**
     * Sanitize class attribute
     */
    private function sanitizeClass(string $class): string
    {
        // Remove any classes that might conflict with our UI
        $blacklistedPrefixes = ['fixed', 'absolute', 'z-', 'pointer-events-none'];

        $classes = explode(' ', $class);
        $filtered = array_filter($classes, function ($c) use ($blacklistedPrefixes) {
            foreach ($blacklistedPrefixes as $prefix) {
                if (strpos($c, $prefix) === 0) {
                    return false;
                }
            }

            return true;
        });

        return implode(' ', $filtered);
    }

    /**
     * Sanitize <style> tag content
     */
    private function sanitizeStyleTag(\DOMElement $node): void
    {
        $content = $node->textContent;

        // Remove dangerous CSS
        $content = preg_replace('/javascript\s*:/i', '', $content);
        $content = preg_replace('/expression\s*\(/i', '', $content);
        $content = preg_replace('/@import/i', '', $content);
        $content = preg_replace('/position\s*:\s*fixed/i', 'position: relative', $content);

        // Don't scope styles for email content as it breaks email-specific CSS
        // Email styles often use specific selectors that shouldn't be modified

        $node->textContent = $content;
    }
}
