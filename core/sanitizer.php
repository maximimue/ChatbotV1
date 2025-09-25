<?php

declare(strict_types=1);

if (!function_exists('chatbot_sanitize_bot_answer')) {
    /**
     * Sanitize HTML returned by the language model so only a small, safe subset remains.
     */
    function chatbot_sanitize_bot_answer(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $allowedTags = ['a', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'p'];
        $allowedTagMap = [];
        foreach ($allowedTags as $tag) {
            $allowedTagMap[$tag] = true;
        }

        $allowedAttributes = [
            'a' => ['href', 'title'],
        ];
        $allowedAttrMap = [];
        foreach ($allowedAttributes as $tag => $attrs) {
            $tag = strtolower($tag);
            $allowedAttrMap[$tag] = [];
            foreach ($attrs as $attr) {
                $allowedAttrMap[$tag][strtolower($attr)] = true;
            }
        }

        $stripList = '<' . implode('><', array_map('strtolower', $allowedTags)) . '>';
        $stripped = strip_tags($html, $stripList);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><div>' . $stripped . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        if ($previous !== null) {
            libxml_use_internal_errors($previous);
        }

        $root = $doc->documentElement;
        if (!$root) {
            return '';
        }

        $clean = function (DOMNode $node) use (&$clean, $doc, $allowedTagMap, $allowedAttrMap): void {
            for ($child = $node->firstChild; $child !== null; $child = $next) {
                $next = $child->nextSibling;

                if ($child instanceof DOMElement) {
                    $tag = strtolower($child->tagName);
                    if (!isset($allowedTagMap[$tag])) {
                        $replacement = $doc->createTextNode($child->textContent ?? '');
                        $node->replaceChild($replacement, $child);
                        continue;
                    }

                    $allowedAttrs = $allowedAttrMap[$tag] ?? [];
                    if ($child->hasAttributes()) {
                        for ($i = $child->attributes->length - 1; $i >= 0; $i--) {
                            $attr = $child->attributes->item($i);
                            if (!$attr) {
                                continue;
                            }
                            $attrName = strtolower($attr->name);
                            if (!isset($allowedAttrs[$attrName])) {
                                $child->removeAttributeNode($attr);
                                continue;
                            }

                            $value = trim((string) $attr->value);
                            if ($tag === 'a' && $attrName === 'href') {
                                $lower = strtolower($value);
                                $isAllowedScheme = (strpos($lower, 'http://') === 0)
                                    || (strpos($lower, 'https://') === 0)
                                    || (strpos($lower, 'mailto:') === 0)
                                    || (strpos($lower, 'tel:') === 0);
                                if (!$isAllowedScheme) {
                                    $child->removeAttributeNode($attr);
                                    continue;
                                }
                                $child->setAttribute($attrName, $value);
                            } else {
                                $child->setAttribute($attrName, $value);
                            }
                        }
                    }

                    if ($tag === 'a') {
                        $child->setAttribute('rel', 'noopener noreferrer');
                        $child->setAttribute('target', '_blank');
                    }

                    $clean($child);
                } elseif ($child instanceof DOMComment) {
                    $node->removeChild($child);
                }
            }
        };

        $clean($root);

        $htmlOut = '';
        foreach ($root->childNodes as $child) {
            $htmlOut .= $doc->saveHTML($child);
        }

        return trim($htmlOut);
    }
}
