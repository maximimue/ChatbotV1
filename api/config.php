<?php
// API configuration for chatbot.syltwerk.de
// Prefer setting the OPENAI_API_KEY via Plesk > Websites & Domains > Hosting & DNS > Apache & nginx settings
// or via Environment Variables. This file provides a fallback placeholder.

$OPENAI_API_KEY = getenv('OPENAI_API_KEY') ?: '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';
$OPENAI_MODEL   = getenv('OPENAI_MODEL') ?: 'gpt-4o-mini';
$ALLOWED_ORIGIN = 'https://chatbot.syltwerk.de'; // CORS: allow same origin

// Safety: hard-limit max prompt size
$MAX_CONTEXT_CHARS = 12000;
$MAX_QUESTION_CHARS = 2000;
